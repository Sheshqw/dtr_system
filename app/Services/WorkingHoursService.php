<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WorkingHoursService
{
    public function computeDailyDtr(User $employee, string $workDate): DailyDtr
    {
        $date       = Carbon::parse($workDate);
        $shift      = $employee->shift;
        $overnight  = $this->isOvernightShift($shift);

        // A normal shift's punches all land within one calendar day, so the
        // existing midnight-to-midnight window is fine. An overnight shift
        // (e.g. 22:00 in / 06:00 out) spans two calendar dates — anchoring
        // the window at noon instead of midnight is safe because a real
        // overnight shift never crosses midday, and it needs no per-shift
        // buffer tuning to work.
        if ($overnight) {
            $windowStart = $date->copy()->setTime(12, 0);
            $windowEnd   = $windowStart->copy()->addDay();
        } else {
            $windowStart = $date->copy()->startOfDay();
            $windowEnd   = $date->copy()->endOfDay();
        }

        $punches = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$windowStart, $windowEnd])
            ->orderBy('punch_time')
            ->get();

        // whereDate() matches regardless of whether the stored value is
        // '2026-06-01' or '2026-06-01 00:00:00' (SQLite text quirk).
        $dtr = DailyDtr::where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first()
            ?? new DailyDtr(['employee_id' => $employee->id, 'work_date' => $workDate]);

        // ── Weekend ──────────────────────────────────────────────────────────
        if ($date->isWeekend()) {
            $dtr->status = 'weekend';
            $dtr->save();
            return $dtr;
        }

        // ── Holiday ──────────────────────────────────────────────────────────
        if (Holiday::whereDate('date', $workDate)->exists()) {
            $dtr->status = 'holiday';
            $dtr->save();
            return $dtr;
        }

        // ── No punches ───────────────────────────────────────────────────────
        if ($punches->isEmpty()) {
            if ($dtr->exists && in_array($dtr->status, ['on_leave', 'holiday'])) {
                return $dtr;
            }
            $dtr->status             = 'absent';
            $dtr->hours_rendered     = 0;
            $dtr->tardiness_minutes  = 0;
            $dtr->overtime_minutes   = 0;
            $dtr->undertime_minutes  = 0;
            $dtr->morning_in         = null;
            $dtr->morning_out        = null;
            $dtr->afternoon_in       = null;
            $dtr->afternoon_out      = null;
            $dtr->save();
            return $dtr;
        }

        // ── Slot assignment ──────────────────────────────────────────────────
        // Always use strict chronological order (1st, 2nd, 3rd, 4th punch).
        // Reason: many ZKTeco/GRANDING devices send status=0 (IN) for every
        // tap; splitting by punch_type produces non-chronological slots
        // (e.g. PM_in before AM_out) whenever punches don't form clean pairs.
        $ordered = $punches->sortBy('punch_time')->values();
        $slots   = [$ordered->get(0), $ordered->get(1), $ordered->get(2), $ordered->get(3)];

        $dtr->morning_in    = $slots[0]?->punch_time->format('H:i:s');
        $dtr->morning_out   = $slots[1]?->punch_time->format('H:i:s');
        $dtr->afternoon_in  = $slots[2]?->punch_time->format('H:i:s');
        $dtr->afternoon_out = $slots[3]?->punch_time->format('H:i:s');

        // ── Hours rendered ───────────────────────────────────────────────────
        // Computed from the punches' own full timestamps (not "$workDate
        // $time" strings) so an overnight pair spanning midnight still gets
        // the correct duration instead of a bogus/negative same-day diff.
        $totalMinutes = 0;

        if ($slots[0] && $slots[1]) {
            $totalMinutes += max(0, $slots[0]->punch_time->diffInMinutes($slots[1]->punch_time));
        }

        if ($slots[2] && $slots[3]) {
            $totalMinutes += max(0, $slots[2]->punch_time->diffInMinutes($slots[3]->punch_time));
        }

        $requiredMinutes = ($shift?->required_hours ?? 8) * 60;
        $totalMinutes    = round($totalMinutes); // whole minutes only

        $dtr->hours_rendered    = round($totalMinutes / 60, 2);
        $dtr->overtime_minutes  = max(0, (int) ($totalMinutes - $requiredMinutes));
        $dtr->undertime_minutes = max(0, (int) ($requiredMinutes - $totalMinutes));

        // ── Tardiness ────────────────────────────────────────────────────────
        $tardiness = 0;
        if ($shift && $dtr->morning_in) {
            $officialStart = Carbon::parse("{$workDate} {$shift->morning_in}");
            $graceEnd      = $officialStart->copy()->addMinutes($shift->grace_period_minutes);
            $actualIn      = $slots[0]->punch_time;

            if ($actualIn->gt($graceEnd)) {
                // $officialStart->diffInMinutes($actualIn) = positive when employee is late
                $tardiness = (int) round($officialStart->diffInMinutes($actualIn));
            }
        }
        $dtr->tardiness_minutes = $tardiness;
        $dtr->status = $tardiness > 0 ? 'late' : 'present';

        $dtr->save();
        return $dtr;
    }

    /**
     * Which work_date a punch belongs to. For an overnight shift, a punch
     * in the early-morning hours (e.g. a 06:00 time-out) belongs to the
     * PREVIOUS day's shift, not the calendar day it physically falls on —
     * without this, that punch would recompute the wrong DTR row and never
     * join up with the 22:00 time-in that started the same shift.
     */
    public function resolveWorkDate(User $employee, Carbon $punchTime): string
    {
        if ($this->isOvernightShift($employee->shift) && $punchTime->hour < 12) {
            return $punchTime->copy()->subDay()->toDateString();
        }

        return $punchTime->toDateString();
    }

    /**
     * Atomically create an AttendanceLog unless one already exists for this
     * employee within +/-5 minutes. Wrapping the check and the insert in one
     * transaction (with a row lock on the check) closes the race where two
     * concurrent requests — a retried ADMS packet, a double device tap — both
     * pass the check before either commits, producing two rows for one punch.
     */
    public function recordPunchIfNotDuplicate(int $employeeId, Carbon $punchTime, array $attributes): ?AttendanceLog
    {
        return DB::transaction(function () use ($employeeId, $punchTime, $attributes) {
            $duplicate = AttendanceLog::where('employee_id', $employeeId)
                ->whereBetween('punch_time', [
                    $punchTime->copy()->subMinutes(5),
                    $punchTime->copy()->addMinutes(5),
                ])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                return null;
            }

            return AttendanceLog::create(array_merge($attributes, [
                'employee_id' => $employeeId,
                'punch_time'  => $punchTime,
            ]));
        });
    }

    private function isOvernightShift(?Shift $shift): bool
    {
        if (! $shift || ! $shift->morning_in || ! $shift->afternoon_out) {
            return false;
        }

        return Carbon::parse($shift->afternoon_out)->lt(Carbon::parse($shift->morning_in));
    }
}

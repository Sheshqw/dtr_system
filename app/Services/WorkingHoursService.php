<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;

class WorkingHoursService
{
    public function computeDailyDtr(User $employee, string $workDate): DailyDtr
    {
        $date    = Carbon::parse($workDate);
        $shift   = $employee->shift;
        $punches = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('punch_time', $workDate)
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

        $dtr->morning_in    = $ordered->get(0)?->punch_time->format('H:i:s');
        $dtr->morning_out   = $ordered->get(1)?->punch_time->format('H:i:s');
        $dtr->afternoon_in  = $ordered->get(2)?->punch_time->format('H:i:s');
        $dtr->afternoon_out = $ordered->get(3)?->punch_time->format('H:i:s');

        // ── Hours rendered ───────────────────────────────────────────────────
        // diffInMinutes($b) in Carbon 3 returns ($b − $this): positive when $b
        // is later. Always call $earlier->diffInMinutes($later).
        $totalMinutes = 0;

        if ($dtr->morning_in && $dtr->morning_out) {
            $in  = Carbon::parse("{$workDate} {$dtr->morning_in}");
            $out = Carbon::parse("{$workDate} {$dtr->morning_out}");
            $totalMinutes += max(0, $in->diffInMinutes($out));
        }

        if ($dtr->afternoon_in && $dtr->afternoon_out) {
            $in  = Carbon::parse("{$workDate} {$dtr->afternoon_in}");
            $out = Carbon::parse("{$workDate} {$dtr->afternoon_out}");
            $totalMinutes += max(0, $in->diffInMinutes($out));
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
            $actualIn      = Carbon::parse("{$workDate} {$dtr->morning_in}");

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
}

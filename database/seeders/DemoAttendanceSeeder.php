<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for presentations/testing: 3 employees with one full, varied
 * month of attendance — on-time, late, undertime, absent, one On Leave day,
 * and one holiday all appear somewhere in the set. Every daily_dtr row is
 * produced by actually punching (AttendanceLog + WorkingHoursService), never
 * written directly, so it reflects real computed behavior.
 *
 * Deliberately NOT called from DatabaseSeeder — run it on demand with:
 *   php artisan db:seed --class=DemoAttendanceSeeder
 */
class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $workingHours = app(WorkingHoursService::class);

        $campus = Campus::firstOrCreate(
            ['code' => 'DEMO'],
            ['name' => 'Demo Campus', 'is_active' => true]
        );

        $department = Department::firstOrCreate(
            ['code' => 'DEMO-CCS'],
            ['name' => 'Demo College of Computer Studies', 'campus_id' => $campus->id]
        );

        $shift = Shift::firstOrCreate(
            ['name' => 'Demo Standard Shift'],
            [
                'morning_in'           => '08:00:00',
                'morning_out'          => '12:00:00',
                'afternoon_in'         => '13:00:00',
                'afternoon_out'        => '17:00:00',
                'grace_period_minutes' => 15,
                'required_hours'       => 8.00,
            ]
        );

        // The most recently fully-elapsed calendar month, so "one complete
        // month" never reaches into days that haven't happened yet.
        $month   = now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $weekdays = collect();
        for ($d = $month->copy(); $d->lte($monthEnd); $d->addDay()) {
            if (! $d->isWeekend()) $weekdays->push($d->copy());
        }

        // One weekday near the middle of the month is a declared holiday —
        // applies to all three employees, same as a real one would. Plain
        // firstOrCreate(['date' => ...]) doesn't work here: SQLite stores
        // the cast date as '2026-09-16 00:00:00' but a bare where('date', …)
        // compares against the raw '2026-09-16' as text and never matches on
        // a re-run — the same quirk WorkingHoursService's own whereDate()
        // calls already work around.
        $holidayDate = $weekdays[intdiv($weekdays->count(), 2)]->toDateString();
        if (! Holiday::whereDate('date', $holidayDate)->exists()) {
            Holiday::create(['date' => $holidayDate, 'name' => 'Demo Founding Anniversary', 'type' => 'regular']);
        }

        // One weekday for the On Leave demo — distinct from the holiday, and
        // from the 'mixed' pattern's own undertime/absent indices (5 and 10)
        // below, so the On Leave day doesn't pre-empt one of those before
        // punchForDay() ever gets to assign it.
        $leaveDate = $weekdays[2]->toDateString();

        $employees = [
            ['employee_id' => 'DEMO-0001', 'first_name' => 'Ana',  'last_name' => 'Villareal', 'pattern' => 'reliable'],
            ['employee_id' => 'DEMO-0002', 'first_name' => 'Mark', 'last_name' => 'Ibarra',    'pattern' => 'tardy'],
            ['employee_id' => 'DEMO-0003', 'first_name' => 'Liza', 'last_name' => 'Concepcion','pattern' => 'mixed'],
        ];

        $hashedPassword = Hash::make('Demo@12345');

        foreach ($employees as $index => $info) {
            $employee = User::firstOrCreate(
                ['employee_id' => $info['employee_id']],
                [
                    'first_name'        => $info['first_name'],
                    'last_name'         => $info['last_name'],
                    'email'             => strtolower("{$info['first_name']}.{$info['last_name']}@demo.nemsu.edu.ph"),
                    'password'          => $hashedPassword,
                    'role'              => 'employee',
                    'campus_id'         => $campus->id,
                    'department_id'     => $department->id,
                    'shift_id'          => $shift->id,
                    'position'          => 'Instructor I',
                    'staff_type'        => 'faculty',
                    'employment_status' => 'permanent',
                    'is_active'         => true,
                ]
            );

            // The third employee demonstrates the On Leave day; the others
            // just work (or don't) that day like any other.
            $leaveDemoEmployee = $index === 2;

            foreach ($weekdays as $weekdayIndex => $day) {
                $dateStr = $day->toDateString();

                if ($dateStr === $holidayDate) {
                    continue; // no punches on a holiday — computeDailyDtr() below marks it regardless
                }

                if ($leaveDemoEmployee && $dateStr === $leaveDate) {
                    // Same effect as the self-service "Mark as On Leave"
                    // endpoint (AttendanceController::markOnLeave): no
                    // punches, status set directly to on_leave. Looked up via
                    // whereDate() rather than a plain updateOrCreate() match
                    // on work_date, for the same reason as the Holiday lookup
                    // above — the cast date is stored with a time component.
                    $dtr = \App\Models\DailyDtr::where('employee_id', $employee->id)
                        ->whereDate('work_date', $dateStr)
                        ->first() ?? new \App\Models\DailyDtr(['employee_id' => $employee->id, 'work_date' => $dateStr]);

                    $dtr->fill([
                        'status'            => 'on_leave',
                        'morning_in'        => null,
                        'morning_out'       => null,
                        'afternoon_in'      => null,
                        'afternoon_out'     => null,
                        'hours_rendered'    => 0,
                        'tardiness_minutes' => 0,
                        'overtime_minutes'  => 0,
                        'undertime_minutes' => 0,
                    ])->save();
                    continue;
                }

                $this->punchForDay($workingHours, $employee, $day, $info['pattern'], $weekdayIndex);
                $workingHours->computeDailyDtr($employee, $dateStr);
            }

            // Compute the holiday day too — no punches needed, Holiday
            // detection in WorkingHoursService handles it for every employee.
            $workingHours->computeDailyDtr($employee, $holidayDate);
        }
    }

    /**
     * Punches (or deliberately doesn't) for one employee on one weekday.
     * Scenarios are assigned by position within the month's weekday list
     * (0, 1, 2, …) rather than by calendar day number — a modulo on the
     * actual day-of-month can land entirely on weekends in a given month
     * (e.g. every "day % 7 === 5" date falling on a Saturday/Sunday that
     * month) and silently produce zero of that scenario; position within
     * the already-weekends-filtered list can't do that.
     */
    private function punchForDay(WorkingHoursService $workingHours, User $employee, Carbon $day, string $pattern, int $weekdayIndex): void
    {
        $scenario = match ($pattern) {
            // Reliable: on time throughout, one absence near the end.
            'reliable' => $weekdayIndex === 15 ? 'absent' : 'on_time',
            // Tardy: late roughly every third day.
            'tardy'    => $weekdayIndex % 3 === 0 ? 'late' : 'on_time',
            // Mixed: one undertime day, one absence, on time otherwise.
            'mixed'    => match ($weekdayIndex) {
                5       => 'undertime',
                10      => 'absent',
                default => 'on_time',
            },
            default => 'on_time',
        };

        if ($scenario === 'absent') {
            return; // no punches at all
        }

        $morningIn    = $day->copy()->setTime(7, 55);
        $morningOut   = $day->copy()->setTime(12, 0);
        $afternoonIn  = $day->copy()->setTime(13, 0);
        $afternoonOut = $day->copy()->setTime(17, 5);

        if ($scenario === 'late') {
            $morningIn = $day->copy()->setTime(8, 30); // past the 15-min grace period
        }

        if ($scenario === 'undertime') {
            $afternoonOut = $day->copy()->setTime(15, 30); // leaves early
        }

        foreach ([$morningIn, $morningOut, $afternoonIn, $afternoonOut] as $punchTime) {
            // 'manual' is the closest valid source enum value for seeded
            // demo data — 'biometric' would misrepresent these as real
            // device taps.
            $workingHours->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => 'IN',
                'source'     => 'manual',
            ]);
        }
    }
}

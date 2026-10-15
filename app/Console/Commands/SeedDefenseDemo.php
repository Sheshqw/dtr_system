<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Fills in a full month of attendance for a real employee (gaps only — never
 * touches a date that already has a punch) plus a handful of clearly-marked
 * demo employees in the same campus/department, so a defense/demo has a
 * complete-looking DTR to show. Safe to run against production:
 *   - Never creates a Holiday (that's campus-wide and would misattribute
 *     every other real employee's day too).
 *   - Never overwrites an existing AttendanceLog for the real employee.
 *   - Demo employees use an "DEF-DEMO-" employee_id prefix and can be fully
 *     removed afterward with --cleanup.
 *
 * Usage:
 *   php artisan defense:seed-demo 2023-02279
 *   php artisan defense:seed-demo 2023-02279 --demo-count=4
 *   php artisan defense:seed-demo --cleanup
 */
class SeedDefenseDemo extends Command
{
    protected $signature = 'defense:seed-demo
        {employee_id? : Your real employee_id to fill in October for}
        {--year=2026}
        {--month=10}
        {--demo-count=4 : How many extra demo employees to create}
        {--cleanup : Remove every DEF-DEMO- employee and their attendance, then exit}';

    protected $description = 'Fill a full month of attendance for a real employee + clearly-marked demo employees (defense prep)';

    private const DEMO_PREFIX = 'DEF-DEMO-';

    public function handle(WorkingHoursService $workingHours): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $employeeId = $this->argument('employee_id');
        if (! $employeeId) {
            $this->error('Pass your employee_id, e.g. php artisan defense:seed-demo 2023-02279');
            return self::FAILURE;
        }

        $employee = User::where('employee_id', $employeeId)->first();
        if (! $employee) {
            $this->error("No employee found with employee_id \"{$employeeId}\".");
            return self::FAILURE;
        }
        if (! $employee->shift_id) {
            $this->error("{$employee->full_name} has no shift assigned — assign one first so working hours can be computed.");
            return self::FAILURE;
        }

        $year  = (int) $this->option('year');
        $month = (int) $this->option('month');
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $weekdays = collect();
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            if (! $d->isWeekend()) $weekdays->push($d->copy());
        }

        $this->info("Filling {$monthStart->format('F Y')} for {$employee->full_name} ({$employee->employee_id}) — gaps only, existing punches are left alone.");

        // One weekday (not already a holiday, not already punched) shows off
        // the self-service On Leave feature on the real account.
        $leaveDate = null;
        foreach ($weekdays as $day) {
            $dateStr = $day->toDateString();
            if (Holiday::whereDate('date', $dateStr)->exists()) continue;
            if (AttendanceLog::where('employee_id', $employee->id)->whereDate('punch_time', $dateStr)->exists()) continue;
            $leaveDate = $dateStr;
            break;
        }

        $filledDates = [];
        foreach ($weekdays as $day) {
            $dateStr = $day->toDateString();

            if (AttendanceLog::where('employee_id', $employee->id)->whereDate('punch_time', $dateStr)->exists()) {
                continue; // real punch already exists — never touch it
            }

            if (Holiday::whereDate('date', $dateStr)->exists()) {
                $workingHours->computeDailyDtr($employee, $dateStr); // holiday already declared, just make sure it's reflected
                continue;
            }

            if ($dateStr === $leaveDate) {
                $this->markOnLeave($employee, $dateStr);
                $filledDates[] = "{$dateStr} (on leave)";
                continue;
            }

            $this->punchOnTime($workingHours, $employee, $day);
            $workingHours->computeDailyDtr($employee, $dateStr);
            $filledDates[] = $dateStr;
        }

        $this->line('Filled ' . count($filledDates) . ' day(s) for your own account:');
        foreach ($filledDates as $d) $this->line("  - {$d}");

        // ── Demo employees — clearly marked, attached to the same campus/department/shift ──
        $demoCount = max(0, (int) $this->option('demo-count'));
        $patterns  = ['reliable', 'tardy', 'mixed', 'undertime'];
        $names     = [
            ['first_name' => 'Ana',    'last_name' => 'Villareal'],
            ['first_name' => 'Mark',   'last_name' => 'Ibarra'],
            ['first_name' => 'Liza',   'last_name' => 'Concepcion'],
            ['first_name' => 'Carlo',  'last_name' => 'Dizon'],
            ['first_name' => 'Rhea',   'last_name' => 'Santiago'],
            ['first_name' => 'Noel',   'last_name' => 'Fernandez'],
        ];

        $hashedPassword = Hash::make('Demo@12345');

        for ($i = 0; $i < $demoCount; $i++) {
            $name    = $names[$i % count($names)];
            $pattern = $patterns[$i % count($patterns)];
            $empId   = self::DEMO_PREFIX . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            $demoEmployee = User::firstOrCreate(
                ['employee_id' => $empId],
                [
                    'first_name'        => $name['first_name'],
                    'last_name'         => $name['last_name'],
                    'email'             => strtolower("{$name['first_name']}.{$name['last_name']}.{$i}@demo.local"),
                    'password'          => $hashedPassword,
                    'role'              => 'employee',
                    'campus_id'         => $employee->campus_id,
                    'department_id'     => $employee->department_id,
                    'shift_id'          => $employee->shift_id,
                    'position'          => 'Instructor I',
                    'staff_type'        => 'faculty',
                    'employment_status' => 'permanent',
                    'is_active'         => true,
                ]
            );

            foreach ($weekdays as $wi => $day) {
                $dateStr = $day->toDateString();
                if (Holiday::whereDate('date', $dateStr)->exists()) {
                    $workingHours->computeDailyDtr($demoEmployee, $dateStr);
                    continue;
                }
                $this->punchPattern($workingHours, $demoEmployee, $day, $pattern, $wi);
                $workingHours->computeDailyDtr($demoEmployee, $dateStr);
            }

            $this->line("Demo employee ready: {$demoEmployee->full_name} ({$empId}) — pattern: {$pattern}");
        }

        $this->newLine();
        $this->info('Done. Run with --cleanup any time to remove every DEF-DEMO- employee and their attendance.');

        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        $ids = User::where('employee_id', 'like', self::DEMO_PREFIX . '%')->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('No DEF-DEMO- employees found — nothing to clean up.');
            return self::SUCCESS;
        }

        AttendanceLog::whereIn('employee_id', $ids)->delete();
        DailyDtr::whereIn('employee_id', $ids)->delete();
        $count = User::whereIn('id', $ids)->delete();

        $this->info("Removed {$count} demo employee(s) and their attendance records.");
        return self::SUCCESS;
    }

    private function markOnLeave(User $employee, string $dateStr): void
    {
        $dtr = DailyDtr::where('employee_id', $employee->id)
            ->whereDate('work_date', $dateStr)
            ->first() ?? new DailyDtr(['employee_id' => $employee->id, 'work_date' => $dateStr]);

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
    }

    private function punchOnTime(WorkingHoursService $workingHours, User $employee, Carbon $day): void
    {
        foreach ([
            $day->copy()->setTime(7, 55),
            $day->copy()->setTime(12, 0),
            $day->copy()->setTime(13, 0),
            $day->copy()->setTime(17, 5),
        ] as $punchTime) {
            $workingHours->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => 'IN',
                'source'     => 'manual',
            ]);
        }
    }

    private function punchPattern(WorkingHoursService $workingHours, User $employee, Carbon $day, string $pattern, int $weekdayIndex): void
    {
        $scenario = match ($pattern) {
            'reliable'  => $weekdayIndex === 15 ? 'absent' : 'on_time',
            'tardy'     => $weekdayIndex % 3 === 0 ? 'late' : 'on_time',
            'mixed'     => match ($weekdayIndex) { 5 => 'undertime', 10 => 'absent', default => 'on_time' },
            'undertime' => $weekdayIndex % 4 === 0 ? 'undertime' : 'on_time',
            default     => 'on_time',
        };

        if ($scenario === 'absent') return;

        $morningIn    = $day->copy()->setTime(7, 55);
        $morningOut   = $day->copy()->setTime(12, 0);
        $afternoonIn  = $day->copy()->setTime(13, 0);
        $afternoonOut = $day->copy()->setTime(17, 5);

        if ($scenario === 'late')      $morningIn    = $day->copy()->setTime(8, 30);
        if ($scenario === 'undertime') $afternoonOut = $day->copy()->setTime(15, 30);

        foreach ([$morningIn, $morningOut, $afternoonIn, $afternoonOut] as $punchTime) {
            $workingHours->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => 'IN',
                'source'     => 'manual',
            ]);
        }
    }
}

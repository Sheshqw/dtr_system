<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Department;
use App\Models\DtrPrintConsent;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fills one past month of attendance for a defense/demo, so the dashboards,
 * reports and Form 48 have a complete, varied month to show. Safe to run
 * against production:
 *   - Gaps only: a weekday that already has a punch, an On Leave mark or a
 *     holiday is never touched, and today/future days are never filled.
 *   - Never creates a Holiday — that's campus-wide and would misattribute
 *     every other employee's day too.
 *   - Punch times are built from each employee's own shift and grace period,
 *     then go through WorkingHoursService like a real punch, so late,
 *     undertime and overtime are exactly what the system computes.
 *   - Every seeded punch carries the remark "Demo data" and every extra demo
 *     employee a DEF-DEMO- employee_id, so --cleanup removes exactly them.
 *
 * System testing: --per-campus=20 tops every active campus up to at least
 * 20 active employees with a realistic roster (faculty, admin staff, guards,
 * COS / Job Order …), then fills their month the same way.
 *
 * Usage:
 *   php artisan defense:seed-demo 2023-02279 --campus=CAN --dry-run
 *   php artisan defense:seed-demo 2023-02279 --campus=CAN --month=9 --year=2026
 *   php artisan defense:seed-demo --per-campus=20
 *   php artisan defense:seed-demo --cleanup
 */
class SeedDefenseDemo extends Command
{
    protected $signature = 'defense:seed-demo
        {employee_ids?* : Showcase account(s) — the full mix (on time, late, absent, On Leave, undertime, overtime) plus a pending print request}
        {--campus= : Also fill every active employee with a shift in this campus (by code), gaps only}
        {--year= : Defaults to the last full month}
        {--month= : Defaults to the last full month}
        {--demo-count=0 : Extra DEF-DEMO- employees to create in the showcase/campus}
        {--per-campus=0 : Top every active campus up to at least this many active employees (HR Officers not counted) with DEF-DEMO- test records}
        {--no-print-request : Skip the pending print request for the showcase account(s)}
        {--dry-run : Show what would be filled, then roll everything back}
        {--cleanup : Remove every seeded punch and DEF-DEMO- employee, then exit}';

    protected $description = 'Fill a past month of realistic attendance for the defense demo (gaps only, removable with --cleanup)';

    public const DEMO_PREFIX  = 'DEF-DEMO-';
    public const DEMO_REMARK  = 'Demo data';
    public const DEMO_PURPOSE = 'Monthly DTR submission (demo)';

    // Weekday positions (0-based, within the days actually being filled) for
    // the showcase mix — spread out so each week has a little of everything.
    private const SHOWCASE = [3 => 'late', 6 => 'undertime', 9 => 'absent', 11 => 'overtime', 13 => 'late', 17 => 'on_leave', 19 => 'overtime'];

    // Rotated across the other employees so no two months look identical.
    private const PATTERNS = ['reliable', 'tardy', 'mixed', 'overtime'];

    private const DEMO_PEOPLE = [
        ['Ana', 'Villareal', 'Instructor I'],
        ['Ramon', 'Ibarra', 'Instructor II'],
        ['Liza', 'Concepcion', 'Assistant Professor I'],
        ['Carlo', 'Dizon', 'Instructor I'],
        ['Rhea', 'Santiago', 'Administrative Aide IV'],
        ['Noel', 'Fernandez', 'Instructor III'],
    ];

    // One campus's test roster — the mix a real NEMSU campus has: mostly
    // faculty, plus admin staff, a guard, utility, a driver, and some COS /
    // Job Order staff so semi-monthly DTRs get exercised too. Cycled when
    // --per-campus asks for more than 20.
    private const ROSTER = [
        ['faculty',        'Instructor I',               'permanent'],
        ['faculty',        'Instructor II',              'permanent'],
        ['faculty',        'Assistant Professor I',      'permanent'],
        ['faculty',        'Instructor I',               'cos'],
        ['administrative', 'Administrative Aide IV',     'permanent'],
        ['faculty',        'Instructor III',             'permanent'],
        ['faculty',        'Associate Professor I',      'permanent'],
        ['administrative', 'Administrative Assistant I', 'job_order'],
        ['faculty',        'Assistant Professor II',     'permanent'],
        ['security',       'Security Guard I',           'job_order'],
        ['faculty',        'Instructor II',              'cos'],
        ['administrative', 'Administrative Officer II',  'permanent'],
        ['laboratory',     'Laboratory Technician I',    'permanent'],
        ['faculty',        'Instructor I',               'permanent'],
        ['custodial',      'Utility Worker I',           'job_order'],
        ['faculty',        'Instructor III',             'permanent'],
        ['driver',         'Driver I',                   'permanent'],
        ['administrative', 'Records Officer I',          'permanent'],
        ['maintenance',    'Maintenance Worker',         'job_order'],
        ['faculty',        'Assistant Professor I',      'permanent'],
    ];

    // Paired so every test employee gets a distinct name (see testName()).
    private const FIRST_NAMES = [
        'Maria', 'Jose', 'Ana', 'Juan', 'Rosa', 'Carlo', 'Liza', 'Ramon', 'Grace', 'Noel',
        'Joy', 'Mark', 'Rhea', 'Paolo', 'Jenny', 'Rey', 'Cherry', 'Arnel', 'Kristine', 'Dennis',
        'Mae', 'Jerome', 'Lovely', 'Ronald', 'Angelica', 'Vincent', 'Marites', 'Edgar', 'Shiela', 'Allan',
    ];
    private const LAST_NAMES = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Ramos',
        'Castillo', 'Flores', 'Navarro', 'Aquino', 'Dela Cruz', 'Gonzales', 'Lopez', 'Rivera', 'Salazar', 'Pascual',
        'Morales', 'Domingo', 'Ibarra', 'Concepcion', 'Dizon', 'Fernandez', 'Villareal', 'Montero', 'Sarmiento', 'Tolentino',
    ];

    public function __construct(private readonly WorkingHoursService $workingHours)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $lastMonth = now()->subMonthNoOverflow();
        $year  = (int) ($this->option('year') ?: $lastMonth->year);
        $month = (int) ($this->option('month') ?: $lastMonth->month);

        if ($month < 1 || $month > 12) {
            $this->error('--month must be between 1 and 12.');
            return self::FAILURE;
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        if ($monthStart->gt(now())) {
            $this->error("{$monthStart->format('F Y')} hasn't started yet — demo data is only filled for days that have already passed.");
            return self::FAILURE;
        }

        $showcase = collect();
        foreach ($this->argument('employee_ids') as $employeeId) {
            $employee = User::where('employee_id', $employeeId)->first();
            if (! $employee) {
                $this->error("No employee found with employee_id \"{$employeeId}\".");
                return self::FAILURE;
            }
            if (! $employee->shift_id) {
                $this->error("{$employee->full_name} has no shift assigned — assign one first so working hours can be computed.");
                return self::FAILURE;
            }
            $showcase->push($employee);
        }

        $campus = null;
        if ($code = $this->option('campus')) {
            $campus = Campus::where('code', $code)->first();
            if (! $campus) {
                $this->error("No campus found with code \"{$code}\".");
                return self::FAILURE;
            }
        }

        $perCampus = max(0, (int) $this->option('per-campus'));

        if ($showcase->isEmpty() && ! $campus && $perCampus === 0) {
            $this->error('Pass a showcase employee_id, --campus=CODE and/or --per-campus=N, e.g. php artisan defense:seed-demo 2023-02279 --campus=CAN');
            return self::FAILURE;
        }

        // Only days that have already passed — never today, which may still
        // receive real punches.
        $days = collect();
        for ($d = $monthStart->copy(); $d->month === $month && $d->lt(today()); $d->addDay()) {
            if (! $d->isWeekend()) $days->push($d->copy());
        }
        if ($days->isEmpty()) {
            $this->error("No weekday of {$monthStart->format('F Y')} has passed yet — nothing to fill.");
            return self::FAILURE;
        }

        $holidays = Holiday::keyedBetween($monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString());
        $dryRun   = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Filling {$monthStart->format('F Y')} — gaps only, existing records are left alone.");
        $this->line($holidays
            ? 'Declared holidays this month: ' . collect($holidays)->map->label()->implode(', ')
            : 'No holidays are declared this month — none will be created.');

        DB::beginTransaction();

        try {
            $rows   = [];
            $filled = collect();

            foreach ($showcase as $employee) {
                $rows[] = $this->fill($employee, $days, $holidays, 'showcase');
                $filled->push($employee->id);
            }

            if ($campus) {
                $others = User::where('campus_id', $campus->id)
                    ->where('role', '!=', 'super_admin')
                    ->where('is_active', true)
                    ->where('employee_id', 'not like', self::DEMO_PREFIX . '%')
                    ->whereNotIn('id', $filled)
                    ->orderBy('id')
                    ->get();

                foreach ($others->values() as $i => $employee) {
                    if (! $employee->shift_id) {
                        $this->warn("Skipped {$employee->full_name} ({$employee->employee_id}) — no shift assigned.");
                        continue;
                    }
                    $rows[] = $this->fill($employee, $days, $holidays, self::PATTERNS[$i % count(self::PATTERNS)]);
                }
            }

            foreach ($this->demoEmployees($showcase->first(), $campus) as $i => $employee) {
                $rows[] = $this->fill($employee, $days, $holidays, self::PATTERNS[$i % count(self::PATTERNS)]);
            }

            if ($perCampus > 0) {
                foreach ($this->topUpCampuses($perCampus) as $i => $employee) {
                    $rows[] = $this->fill($employee, $days, $holidays, self::PATTERNS[$i % count(self::PATTERNS)]);
                }
            }

            $requests = $this->option('no-print-request')
                ? []
                : $showcase->map(fn (User $e) => $this->requestPrint($e, $year, $month))->filter()->all();

            $this->newLine();
            $this->table(
                ['Employee', 'Pattern', 'Days filled', 'Present', 'Late', 'Absent', 'On leave', 'Holiday'],
                $rows,
            );
            foreach ($requests as $line) $this->line($line);

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run — nothing was saved. Run again without --dry-run to apply.');
                return self::SUCCESS;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->newLine();
        $this->info('Done. Run php artisan defense:seed-demo --cleanup any time to remove it all.');

        return self::SUCCESS;
    }

    /**
     * Fill one employee's gaps for the month and report how the month came
     * out, as computed by the system itself.
     */
    private function fill(User $employee, Collection $days, array $holidays, string $pattern): array
    {
        $employee->loadMissing('shift');
        $shift = $employee->shift;

        if ($this->isOvernight($shift)) {
            $this->warn("Skipped {$employee->full_name} ({$employee->employee_id}) — overnight shifts aren't supported by the demo seeder.");
            return [$employee->full_name, $pattern, 0, '—', '—', '—', '—', '—'];
        }

        // A day's scenario comes from its fixed position among the month's
        // working days, not among the still-empty ones — so a re-run gives a
        // deliberately absent day the same "absent" again instead of filling it.
        $workdays = $days->reject(fn (Carbon $d) => isset($holidays[$d->toDateString()]))->values();

        $open = $workdays->filter(function (Carbon $day) use ($employee) {
            $date = $day->toDateString();
            if (AttendanceLog::where('employee_id', $employee->id)->whereDate('punch_time', $date)->exists()) return false;

            return ! DailyDtr::where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->whereIn('status', ['on_leave', 'holiday'])
                ->exists();
        });

        foreach ($open as $i => $day) {
            $scenario = $this->scenario($pattern, $i);
            $date     = $day->toDateString();

            // Same times for the same person and day on every run.
            mt_srand(crc32("{$employee->employee_id}|{$date}"));

            if ($scenario === 'on_leave') {
                $this->markOnLeave($employee, $date);
                continue;
            }

            if ($scenario !== 'absent') {
                foreach ($this->punchTimes($shift, $day, $scenario) as $punchTime) {
                    $this->workingHours->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                        'punch_type' => 'IN',
                        // 'biometric' would misrepresent these as real device taps.
                        'source'     => 'manual',
                        'remarks'    => self::DEMO_REMARK,
                    ]);
                }
            }

            $this->workingHours->computeDailyDtr($employee, $date);
        }

        // A declared holiday with no punches still needs its DTR row.
        foreach (array_keys($holidays) as $date) {
            if ($days->contains(fn (Carbon $d) => $d->toDateString() === $date)) {
                $this->workingHours->computeDailyDtr($employee, $date);
            }
        }

        $statuses = DailyDtr::where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $days->first()?->toDateString())
            ->whereDate('work_date', '<=', $days->last()?->toDateString())
            ->pluck('status')
            ->countBy();

        return [
            "{$employee->full_name} ({$employee->employee_id})",
            $pattern,
            $open->count(),
            $statuses['present'] ?? 0,
            $statuses['late'] ?? 0,
            $statuses['absent'] ?? 0,
            $statuses['on_leave'] ?? 0,
            $statuses['holiday'] ?? 0,
        ];
    }

    private function scenario(string $pattern, int $i): string
    {
        return match ($pattern) {
            'showcase' => self::SHOWCASE[$i] ?? 'on_time',
            'reliable' => $i === 7 ? 'overtime' : 'on_time',
            'tardy'    => $i % 4 === 1 ? 'late' : 'on_time',
            'mixed'    => match ($i) { 5 => 'undertime', 12 => 'absent', 15 => 'late', default => 'on_time' },
            'overtime' => $i % 3 === 0 ? 'overtime' : 'on_time',
            default    => 'on_time',
        };
    }

    /**
     * The day's four taps, relative to the employee's own shift: an on-time
     * arrival stays inside the grace period, a late one lands past it, and
     * consecutive taps are always more than 5 minutes apart so the duplicate
     * guard never swallows one.
     */
    private function punchTimes(Shift $shift, Carbon $day, string $scenario): array
    {
        $at    = fn (string $time) => Carbon::parse($day->toDateString() . ' ' . $time);
        $jit   = fn (Carbon $t, int $min, int $max) => $t->copy()->addMinutes(mt_rand($min, $max))->addSeconds(mt_rand(0, 59));
        $grace = (int) $shift->grace_period_minutes;

        $in = $scenario === 'late'
            ? $jit($at($shift->morning_in), $grace + 3, $grace + 35)
            : $jit($at($shift->morning_in), -20, max(-20, $grace - 2));
        $morningOut = $jit($at($shift->morning_out), 0, 6);
        $afterIn    = $jit($at($shift->afternoon_in), -12, 0);

        // A shift with no lunch gap (e.g. 06:00–12:00 / 12:00–18:30).
        if ($afterIn->lt($morningOut->copy()->addMinutes(6))) {
            $afterIn = $morningOut->copy()->addMinutes(mt_rand(6, 10));
        }

        // Undertime and overtime are measured against required hours, which
        // can be less than the shift's span (the Faculty shift spans 8h40 for
        // 8 required) — so those departures are set from the hours already
        // rendered that morning, not just from the shift's end time.
        $required  = (int) round(((float) ($shift->required_hours ?? 8)) * 60);
        $morning   = (int) $in->diffInMinutes($morningOut);
        $metAt     = $afterIn->copy()->addMinutes(max(0, $required - $morning));
        $shiftEnd  = $at($shift->afternoon_out);

        $out = match ($scenario) {
            // Leaves 30–90 min before both the shift's end and the required hours.
            'undertime' => $metAt->copy()->min($shiftEnd)->subMinutes(mt_rand(30, 90))->addSeconds(mt_rand(0, 59)),
            // Stays 45–100 min past both.
            'overtime'  => $metAt->copy()->max($shiftEnd)->addMinutes(mt_rand(45, 100))->addSeconds(mt_rand(0, 59)),
            default     => $jit($shiftEnd, 0, 15),
        };

        return [$in, $morningOut, $afterIn, $out];
    }

    /** Same effect as the self-service "Mark as On Leave" (AttendanceController::applyOnLeave). */
    private function markOnLeave(User $employee, string $date): void
    {
        $dtr = DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $date)->first()
            ?? new DailyDtr(['employee_id' => $employee->id, 'work_date' => $date]);

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

    /** A pending request for HR to approve live — skipped if one is already open, same rule as the API. */
    private function requestPrint(User $employee, int $year, int $month): ?string
    {
        $open = DtrPrintConsent::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($q2) => $q2->where('status', 'approved')->whereNull('used_at')))
            ->exists();

        if ($open) {
            return "{$employee->full_name} already has an open print request for this month — left as is.";
        }

        DtrPrintConsent::create([
            'employee_id' => $employee->id,
            'month'       => $month,
            'year'        => $year,
            'period'      => 'full',
            'purpose'     => self::DEMO_PURPOSE,
            'status'      => 'pending',
        ]);

        return "Pending print request created for {$employee->full_name} — ready for HR to approve.";
    }

    /** @return Collection<int, User> */
    private function demoEmployees(?User $anchor, ?Campus $campus): Collection
    {
        $count = max(0, (int) $this->option('demo-count'));
        if ($count === 0) return collect();

        $campusId = $anchor?->campus_id ?? $campus?->id;
        $shiftId  = $anchor?->shift_id
            ?? Shift::where('name', 'Faculty / Instructor Shift')->value('id')
            ?? Shift::orderBy('id')->value('id');

        if (! $shiftId) {
            $this->warn('No shift exists to give the demo employees — skipped them.');
            return collect();
        }

        $departments = Department::where(fn ($q) => $q->whereNull('campus_id')->orWhere('campus_id', $campusId))
            ->orderBy('id')->pluck('id');

        return collect(range(1, $count))->map(function (int $n) use ($campusId, $shiftId, $departments) {
            [$first, $last, $position] = self::DEMO_PEOPLE[($n - 1) % count(self::DEMO_PEOPLE)];

            return User::firstOrCreate(
                ['employee_id' => self::DEMO_PREFIX . str_pad((string) $n, 2, '0', STR_PAD_LEFT)],
                [
                    'first_name'        => $first,
                    'last_name'         => $last,
                    'email'             => strtolower("{$first}.{$last}.{$n}@demo.local"),
                    // Never a guessable shared password on a live server.
                    'password'          => Hash::make(Str::password(24)),
                    'role'              => 'employee',
                    'campus_id'         => $campusId,
                    'department_id'     => $departments->isEmpty() ? null : $departments[($n - 1) % $departments->count()],
                    'shift_id'          => $shiftId,
                    'position'          => $position,
                    'staff_type'        => str_starts_with($position, 'Administrative') ? 'administrative' : 'faculty',
                    'employment_status' => 'permanent',
                    'is_active'         => true,
                ]
            );
        })->values();
    }

    /**
     * Enough DEF-DEMO-<CODE>- employees for every active campus to reach
     * $target active (non–Super Admin) employees. Real employees count toward
     * the target, so a campus that already has 20 gets none; re-running
     * reuses the same records instead of adding more.
     *
     * @return Collection<int, User>
     */
    private function topUpCampuses(int $target): Collection
    {
        $created = collect();
        $nameSeq = 0;

        foreach (Campus::where('is_active', true)->orderBy('id')->get() as $campus) {
            $prefix = self::DEMO_PREFIX . $campus->code . '-';

            // Employees only: the HR Officer accounts a campus also has
            // aren't employee records, so they don't count toward the target.
            $real = User::where('campus_id', $campus->id)
                ->where('role', 'employee')
                ->where('is_active', true)
                ->where('employee_id', 'not like', $prefix . '%')
                ->count();

            $needed = max(0, $target - $real);

            $departments = Department::where(fn ($q) => $q->whereNull('campus_id')->orWhere('campus_id', $campus->id))
                ->orderBy('id')->pluck('id');

            for ($n = 1; $n <= $needed; $n++) {
                [$staffType, $position, $status] = self::ROSTER[($n - 1) % count(self::ROSTER)];
                [$first, $last] = $this->testName($nameSeq++);

                $shiftId = $this->shiftFor($staffType);
                if (! $shiftId) {
                    $this->warn('No shift exists to give the test employees — create one first.');
                    return $created;
                }

                $created->push(User::firstOrCreate(
                    ['employee_id' => $prefix . str_pad((string) $n, 2, '0', STR_PAD_LEFT)],
                    [
                        'first_name'        => $first,
                        'last_name'         => $last,
                        // No email: test records never sign in, and approving
                        // their print requests must not send real mail.
                        'email'             => null,
                        // Never a guessable shared password on a live server.
                        'password'          => Hash::make(Str::password(24)),
                        'role'              => 'employee',
                        'campus_id'         => $campus->id,
                        'department_id'     => $departments->isEmpty() ? null : $departments[($n - 1) % $departments->count()],
                        'shift_id'          => $shiftId,
                        'position'          => $position,
                        'staff_type'        => $staffType,
                        'employment_status' => $status,
                        'is_active'         => true,
                    ]
                ));
            }

            $this->line("[{$campus->code}] {$real} real employee(s) + {$needed} test record(s) = " . ($real + $needed));
        }

        return $created;
    }

    /** The n-th distinct first/last pair — unique for the first 900. */
    private function testName(int $n): array
    {
        $count = count(self::FIRST_NAMES);
        $first = $n % $count;
        $last  = ($first + intdiv($n, $count)) % count(self::LAST_NAMES);

        return [self::FIRST_NAMES[$first], self::LAST_NAMES[$last]];
    }

    /** A shift meant for this staff type, else the general-purpose one. */
    private function shiftFor(string $staffType): ?int
    {
        return Shift::where('staff_type', $staffType)->orderBy('id')->value('id')
            ?? Shift::whereNull('staff_type')->orderBy('id')->value('id')
            ?? Shift::orderBy('id')->value('id');
    }

    private function cleanup(): int
    {
        DB::transaction(function () {
            $demoIds = User::where('employee_id', 'like', self::DEMO_PREFIX . '%')->pluck('id');

            // Seeded punches on real accounts: remove them, then recompute
            // each affected day so it goes back to what its real punches say.
            $affected = AttendanceLog::where('remarks', self::DEMO_REMARK)
                ->where('source', 'manual')
                ->whereNotIn('employee_id', $demoIds)
                ->get(['employee_id', 'punch_time'])
                ->map(fn ($log) => [$log->employee_id, $log->punch_time->toDateString()])
                ->unique(fn ($pair) => implode('|', $pair));

            $removed = AttendanceLog::where('remarks', self::DEMO_REMARK)->where('source', 'manual')
                ->whereNotIn('employee_id', $demoIds)->delete();

            foreach ($affected as [$employeeId, $date]) {
                if ($employee = User::find($employeeId)) {
                    $this->workingHours->computeDailyDtr($employee, $date);
                }
            }

            $requests = DtrPrintConsent::where('purpose', self::DEMO_PURPOSE)->where('status', 'pending')->delete();

            AttendanceLog::whereIn('employee_id', $demoIds)->delete();
            DailyDtr::whereIn('employee_id', $demoIds)->delete();
            DtrPrintConsent::whereIn('employee_id', $demoIds)->delete();
            $users = User::whereIn('id', $demoIds)->delete();

            $this->info("Removed {$removed} seeded punch(es) across {$affected->count()} day(s), {$requests} pending demo print request(s) and {$users} DEF-DEMO- employee(s).");
            $this->line('Seeded On Leave days are kept — unmark any of them from the employee\'s Attendance page if needed.');
        });

        return self::SUCCESS;
    }

    private function isOvernight(?Shift $shift): bool
    {
        return $shift && $shift->afternoon_out && $shift->morning_in
            && Carbon::parse($shift->afternoon_out)->lt(Carbon::parse($shift->morning_in));
    }
}

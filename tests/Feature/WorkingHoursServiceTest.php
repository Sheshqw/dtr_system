<?php

use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;

// A fixed, guaranteed weekday so weekend/holiday branches don't accidentally
// trigger — always "next Monday" relative to whenever the suite runs.
function workDate(): string
{
    return Carbon::now()->next(Carbon::MONDAY)->toDateString();
}

function punchAt(User $employee, string $date, string $time): void
{
    AttendanceLog::create([
        'employee_id' => $employee->id,
        'punch_time'  => "{$date} {$time}",
        'punch_type'  => 'IN',
        'source'      => 'manual',
    ]);
}

beforeEach(function () {
    $this->service = app(WorkingHoursService::class);
    $this->shift   = Shift::factory()->create([
        'morning_in'           => '08:00:00',
        'morning_out'          => '12:00:00',
        'afternoon_in'         => '13:00:00',
        'afternoon_out'        => '17:00:00',
        'grace_period_minutes' => 15,
        'required_hours'       => 8.00,
    ]);
    $this->employee = User::factory()->create(['shift_id' => $this->shift->id]);
});

test('a full on-time day computes exactly the required hours with no tardiness', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:00:00');
    punchAt($this->employee, $date, '12:00:00');
    punchAt($this->employee, $date, '13:00:00');
    punchAt($this->employee, $date, '17:00:00');

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect($dtr->status)->toBe('present');
    expect((float) $dtr->hours_rendered)->toBe(8.0);
    expect($dtr->tardiness_minutes)->toBe(0);
    expect($dtr->overtime_minutes)->toBe(0);
    expect($dtr->undertime_minutes)->toBe(0);
});

test('arriving after the grace period is marked late with tardiness measured from shift start', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:20:00'); // 5 min past the 15-min grace window
    punchAt($this->employee, $date, '12:00:00');
    punchAt($this->employee, $date, '13:00:00');
    punchAt($this->employee, $date, '17:00:00');

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect($dtr->status)->toBe('late');
    // Measured from official start (08:00), not from the end of the grace window.
    expect($dtr->tardiness_minutes)->toBe(20);
});

test('arriving within the grace period is not marked late', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:10:00'); // inside the 15-min grace window
    punchAt($this->employee, $date, '12:00:00');
    punchAt($this->employee, $date, '13:00:00');
    punchAt($this->employee, $date, '17:00:00');

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect($dtr->status)->toBe('present');
    expect($dtr->tardiness_minutes)->toBe(0);
});

test('leaving early without an afternoon session records undertime', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:00:00');
    punchAt($this->employee, $date, '12:00:00');
    // no afternoon punches — only 4 worked hours against an 8-hour shift

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect((float) $dtr->hours_rendered)->toBe(4.0);
    expect($dtr->undertime_minutes)->toBe(240);
    expect($dtr->overtime_minutes)->toBe(0);
});

test('working past the required hours records overtime', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:00:00');
    punchAt($this->employee, $date, '12:00:00');
    punchAt($this->employee, $date, '13:00:00');
    punchAt($this->employee, $date, '19:00:00'); // 2 extra hours

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect((float) $dtr->hours_rendered)->toBe(10.0);
    expect($dtr->overtime_minutes)->toBe(120);
    expect($dtr->undertime_minutes)->toBe(0);
});

test('no punches at all marks the day absent', function () {
    $date = workDate();

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect($dtr->status)->toBe('absent');
    expect((float) $dtr->hours_rendered)->toBe(0.0);
});

test('a Saturday is always marked weekend regardless of punches', function () {
    $saturday = Carbon::now()->next(Carbon::SATURDAY)->toDateString();
    punchAt($this->employee, $saturday, '08:00:00');

    $dtr = $this->service->computeDailyDtr($this->employee, $saturday);

    expect($dtr->status)->toBe('weekend');
});

test('a declared holiday overrides an ordinary weekday', function () {
    $date = workDate();
    Holiday::create(['name' => 'Test Holiday', 'date' => $date, 'type' => 'national']);
    punchAt($this->employee, $date, '08:00:00');

    $dtr = $this->service->computeDailyDtr($this->employee, $date);

    expect($dtr->status)->toBe('holiday');
});

test('recomputing the same day is idempotent — it updates the existing row, not a duplicate', function () {
    $date = workDate();
    punchAt($this->employee, $date, '08:00:00');
    punchAt($this->employee, $date, '12:00:00');

    $this->service->computeDailyDtr($this->employee, $date);
    $this->service->computeDailyDtr($this->employee, $date);

    expect(\App\Models\DailyDtr::where('employee_id', $this->employee->id)
        ->whereDate('work_date', $date)->count())->toBe(1);
});

// ── Overnight shifts ─────────────────────────────────────────────────────

describe('overnight shifts', function () {
    beforeEach(function () {
        $this->nightShift = Shift::factory()->create([
            'morning_in'           => '22:00:00',
            'morning_out'          => null,
            'afternoon_in'         => null,
            'afternoon_out'        => '06:00:00',
            'grace_period_minutes' => 15,
            'required_hours'       => 8.00,
        ]);
        $this->guard = User::factory()->create(['shift_id' => $this->nightShift->id]);
    });

    test('a shift crossing midnight computes the correct duration, not a bogus same-day diff', function () {
        $date     = workDate();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();

        punchAt($this->guard, $date, '22:00:00');
        punchAt($this->guard, $nextDate, '06:00:00');

        $dtr = $this->service->computeDailyDtr($this->guard, $date);

        expect((float) $dtr->hours_rendered)->toBe(8.0);
        expect($dtr->overtime_minutes)->toBe(0);
        expect($dtr->undertime_minutes)->toBe(0);
        expect($dtr->status)->toBe('present');
    });

    test('the next calendar day stays absent — the overnight punches are not double-counted', function () {
        $date     = workDate();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();

        punchAt($this->guard, $date, '22:00:00');
        punchAt($this->guard, $nextDate, '06:00:00');

        $this->service->computeDailyDtr($this->guard, $date);
        $nextDtr = $this->service->computeDailyDtr($this->guard, $nextDate);

        expect($nextDtr->status)->toBe('absent');
    });

    test('resolveWorkDate attributes an early-morning out-punch to the previous night\'s shift', function () {
        $date     = workDate();
        $nextDate = Carbon::parse($date)->addDay();

        $resolved = $this->service->resolveWorkDate($this->guard, $nextDate->copy()->setTime(6, 0));

        expect($resolved)->toBe($date);
    });

    test('resolveWorkDate leaves a normal day-shift employee on the punch\'s own calendar date', function () {
        $date = workDate();
        $punchTime = Carbon::parse($date)->setTime(8, 0);

        $resolved = $this->service->resolveWorkDate($this->employee, $punchTime);

        expect($resolved)->toBe($date);
    });
});

// ── Duplicate-punch guard ────────────────────────────────────────────────

test('recordPunchIfNotDuplicate rejects a second punch within the 5-minute window', function () {
    $punchTime = Carbon::parse(workDate() . ' 08:00:00');

    $first  = $this->service->recordPunchIfNotDuplicate($this->employee->id, $punchTime, ['punch_type' => 'IN', 'source' => 'manual']);
    $second = $this->service->recordPunchIfNotDuplicate($this->employee->id, $punchTime->copy()->addMinutes(3), ['punch_type' => 'IN', 'source' => 'manual']);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();
    expect(AttendanceLog::where('employee_id', $this->employee->id)->count())->toBe(1);
});

test('recordPunchIfNotDuplicate allows a punch outside the 5-minute window', function () {
    $punchTime = Carbon::parse(workDate() . ' 08:00:00');

    $first  = $this->service->recordPunchIfNotDuplicate($this->employee->id, $punchTime, ['punch_type' => 'IN', 'source' => 'manual']);
    $second = $this->service->recordPunchIfNotDuplicate($this->employee->id, $punchTime->copy()->addMinutes(10), ['punch_type' => 'OUT', 'source' => 'manual']);

    expect($first)->not->toBeNull();
    expect($second)->not->toBeNull();
    expect(AttendanceLog::where('employee_id', $this->employee->id)->count())->toBe(2);
});

// ── Holiday backfill (self-heal for employees created after a holiday) ──

test('backfillHolidays creates a holiday row for an employee who had none yet', function () {
    $date = workDate();
    Holiday::create(['date' => $date, 'name' => 'Test Holiday', 'type' => 'regular']);

    expect(\App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->exists())->toBeFalse();

    $this->service->backfillHolidays([$this->employee->id], $date, $date);

    $dtr = \App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->first();
    expect($dtr)->not->toBeNull();
    expect($dtr->status)->toBe('holiday');
});

test('backfillHolidays is a no-op once a row is already correctly marked', function () {
    $date = workDate();
    Holiday::create(['date' => $date, 'name' => 'Test Holiday', 'type' => 'regular']);

    $this->service->backfillHolidays([$this->employee->id], $date, $date);
    $firstRun = \App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->first();

    // A second call shouldn't touch (or duplicate) the already-correct row.
    $this->service->backfillHolidays([$this->employee->id], $date, $date);
    $secondRun = \App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->first();

    expect(\App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->count())->toBe(1);
    expect($secondRun->updated_at->equalTo($firstRun->updated_at))->toBeTrue();
});

test('backfillHolidays does nothing when there is no holiday in range', function () {
    $date = workDate();

    $this->service->backfillHolidays([$this->employee->id], $date, $date);

    expect(\App\Models\DailyDtr::where('employee_id', $this->employee->id)->whereDate('work_date', $date)->exists())->toBeFalse();
});

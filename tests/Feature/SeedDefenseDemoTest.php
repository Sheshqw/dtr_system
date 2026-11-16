<?php

use App\Console\Commands\SeedDefenseDemo;
use App\Models\AttendanceLog;
use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\DtrPrintConsent;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 10:00:00');

    $this->campus = Campus::factory()->create(['code' => 'CAN']);
    // The live Faculty / Instructor Shift — starts at 07:00, not 08:00.
    $this->shift = Shift::factory()->create([
        'morning_in' => '07:00:00', 'morning_out' => '11:40:00',
        'afternoon_in' => '13:00:00', 'afternoon_out' => '17:00:00',
        'grace_period_minutes' => 15, 'required_hours' => 8,
    ]);
    $this->faculty = User::factory()->create([
        'employee_id' => '2023-02279', 'campus_id' => $this->campus->id, 'shift_id' => $this->shift->id,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function septStatuses(User $u): array
{
    return DailyDtr::where('employee_id', $u->id)->pluck('status')->countBy()->all();
}

test('the showcase account gets every September weekday with the full mix, timed to its own shift', function () {
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279']])->assertSuccessful();

    // September 2026 has 22 weekdays; a 07:00 shift must not turn them all late.
    expect(septStatuses($this->faculty))->toEqual(['present' => 18, 'late' => 2, 'absent' => 1, 'on_leave' => 1]);

    $logs = AttendanceLog::where('employee_id', $this->faculty->id)->get();
    expect($logs)->toHaveCount(20 * 4)
        ->and($logs->every(fn ($l) => $l->remarks === SeedDefenseDemo::DEMO_REMARK && $l->source === 'manual'))->toBeTrue()
        ->and($logs->contains(fn ($l) => $l->punch_time->isWeekend()))->toBeFalse()
        ->and($logs->every(fn ($l) => $l->punch_time->format('Y-m') === '2026-09'))->toBeTrue();

    $dtrs = DailyDtr::where('employee_id', $this->faculty->id)->get();
    expect($dtrs->where('undertime_minutes', '>=', 30)->count())->toBeGreaterThanOrEqual(1)
        ->and($dtrs->where('overtime_minutes', '>=', 45)->count())->toBeGreaterThanOrEqual(2)
        ->and($dtrs->where('status', 'late')->every(fn ($d) => $d->tardiness_minutes > 15))->toBeTrue();
});

test('it creates one pending print request for HR, and a re-run adds nothing', function () {
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279']])->assertSuccessful();
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279']])->assertSuccessful();

    expect(DtrPrintConsent::where('employee_id', $this->faculty->id)->get())
        ->toHaveCount(1)
        ->first()->toMatchArray(['month' => 9, 'year' => 2026, 'period' => 'full', 'status' => 'pending'])
        ->and(AttendanceLog::count())->toBe(80);
});

test('days that already have real attendance are never touched', function () {
    AttendanceLog::create(['employee_id' => $this->faculty->id, 'punch_time' => '2026-09-01 06:58:00', 'punch_type' => 'IN', 'source' => 'biometric']);
    DailyDtr::create(['employee_id' => $this->faculty->id, 'work_date' => '2026-09-02', 'status' => 'on_leave']);

    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279']])->assertSuccessful();

    expect(AttendanceLog::where('employee_id', $this->faculty->id)->whereDate('punch_time', '2026-09-01')->pluck('source')->all())->toBe(['biometric'])
        ->and(AttendanceLog::where('employee_id', $this->faculty->id)->whereDate('punch_time', '2026-09-02')->exists())->toBeFalse();
});

test('a declared holiday is respected but never created', function () {
    Holiday::create(['date' => '2026-09-15', 'name' => 'Founding Anniversary', 'type' => 'special']);

    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279']])->assertSuccessful();

    expect(Holiday::count())->toBe(1)
        ->and(AttendanceLog::whereDate('punch_time', '2026-09-15')->exists())->toBeFalse()
        ->and(DailyDtr::whereDate('work_date', '2026-09-15')->value('status'))->toBe('holiday');
});

test('--campus fills the other employees with a shift and skips the super admin', function () {
    $others = User::factory()->count(3)->create(['campus_id' => $this->campus->id, 'shift_id' => $this->shift->id]);
    $hr     = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id, 'shift_id' => $this->shift->id]);
    $noShift = User::factory()->create(['campus_id' => $this->campus->id]);
    $admin  = User::factory()->superAdmin()->create(['shift_id' => $this->shift->id]);
    $admin->update(['campus_id' => $this->campus->id]);

    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279'], '--campus' => 'CAN'])->assertSuccessful();

    foreach ([...$others, $hr] as $u) {
        expect(DailyDtr::where('employee_id', $u->id)->count())->toBe(22);
    }
    expect(DailyDtr::whereIn('employee_id', [$noShift->id, $admin->id])->exists())->toBeFalse()
        // Only the showcase account gets a print request.
        ->and(DtrPrintConsent::count())->toBe(1);
});

test('--dry-run saves nothing', function () {
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279'], '--campus' => 'CAN', '--demo-count' => 2, '--dry-run' => true])
        ->assertSuccessful();

    expect(AttendanceLog::count())->toBe(0)
        ->and(DailyDtr::count())->toBe(0)
        ->and(DtrPrintConsent::count())->toBe(0)
        ->and(User::where('employee_id', 'like', 'DEF-DEMO-%')->exists())->toBeFalse();
});

test('--cleanup removes only seeded data and recomputes the affected days', function () {
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279'], '--demo-count' => 2])->assertSuccessful();
    expect(User::where('employee_id', 'like', 'DEF-DEMO-%')->count())->toBe(2);

    // A real punch added afterward must survive the cleanup.
    $real = AttendanceLog::create(['employee_id' => $this->faculty->id, 'punch_time' => '2026-10-20 06:55:00', 'punch_type' => 'IN', 'source' => 'biometric']);

    $this->artisan('defense:seed-demo', ['--cleanup' => true])->assertSuccessful();

    expect(AttendanceLog::pluck('id')->all())->toBe([$real->id])
        ->and(User::where('employee_id', 'like', 'DEF-DEMO-%')->exists())->toBeFalse()
        ->and(DtrPrintConsent::count())->toBe(0)
        ->and(septStatuses($this->faculty))->toEqual(['absent' => 21, 'on_leave' => 1]);
});

test('it refuses a month that has not started', function () {
    $this->artisan('defense:seed-demo', ['employee_ids' => ['2023-02279'], '--month' => 11, '--year' => 2026])
        ->assertFailed();

    expect(AttendanceLog::count())->toBe(0);
});

test('--per-campus tops every active campus up to the target with a varied roster', function () {
    $tan = Campus::factory()->create(['code' => 'TAN']);
    Campus::factory()->create(['code' => 'OLD', 'is_active' => false]);
    User::factory()->count(2)->create(['campus_id' => $this->campus->id, 'shift_id' => $this->shift->id]);
    User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id, 'shift_id' => $this->shift->id]);

    $this->artisan('defense:seed-demo', ['--per-campus' => 20])->assertSuccessful();

    $active = fn (Campus $c) => User::where('campus_id', $c->id)->where('role', 'employee')->where('is_active', true)->count();

    // CAN already had 3 real employees, so it only needed 17 — its HR
    // Officer isn't an employee record and doesn't count toward the 20.
    expect($active($this->campus))->toBe(20)
        ->and($active($tan))->toBe(20)
        ->and(User::where('employee_id', 'like', 'DEF-DEMO-CAN-%')->count())->toBe(17)
        ->and(User::where('employee_id', 'like', 'DEF-DEMO-OLD-%')->exists())->toBeFalse();

    $test = User::where('employee_id', 'like', 'DEF-DEMO-TAN-%')->get();
    expect($test->pluck('staff_type')->unique()->count())->toBeGreaterThan(4)
        ->and($test->whereIn('employment_status', ['cos', 'job_order'])->count())->toBeGreaterThan(0)
        ->and($test->map(fn ($u) => $u->full_name)->unique()->count())->toBe(20)
        // Each one gets a filled month, computed from its shift.
        ->and(DailyDtr::where('employee_id', $test->first()->id)->count())->toBe(22);

    // Re-running reuses the same records; cleanup removes them all.
    $this->artisan('defense:seed-demo', ['--per-campus' => 20])->assertSuccessful();
    expect($active($tan))->toBe(20);

    $this->artisan('defense:seed-demo', ['--cleanup' => true])->assertSuccessful();
    expect(User::where('employee_id', 'like', 'DEF-DEMO-%')->exists())->toBeFalse();
});

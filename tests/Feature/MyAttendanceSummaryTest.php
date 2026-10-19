<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    // Friday, Oct 16 2026 — the window then runs Oct 1 (Thu) to Oct 16.
    Carbon::setTestNow('2026-10-16 18:00:00');

    $this->campus   = Campus::factory()->create(['code' => 'CAN']);
    $this->employee = User::factory()->create([
        'campus_id'  => $this->campus->id,
        'shift_id'   => Shift::factory()->create()->id,
        'date_hired' => '2026-10-01',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function dtr(User $u, string $date, string $status, array $extra = []): void
{
    DailyDtr::create(['employee_id' => $u->id, 'work_date' => $date, 'status' => $status] + $extra);
}

test('the summary counts only days since the hire date and matches the calendar rules', function () {
    // Oct 1–16 has 12 weekdays. Record 9 on time, 2 late, 1 on leave.
    $weekdays = ['01', '02', '05', '06', '07', '08', '09', '12', '13', '14', '15', '16'];
    foreach ($weekdays as $i => $d) {
        $status = match (true) { $i === 3 => 'late', $i === 7 => 'late', $i === 10 => 'on_leave', default => 'present' };
        dtr($this->employee, "2026-10-{$d}", $status, [
            'morning_in'        => $status === 'late' ? '08:25:00' : '07:55:00',
            'hours_rendered'    => $status === 'on_leave' ? 0 : 8,
            'tardiness_minutes' => $status === 'late' ? 10 : 0,
        ]);
    }

    $res = $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/me/attendance-summary')
        ->assertOk()
        ->assertJsonPath('since', '2026-10-01')
        ->assertJsonPath('shift.required_hours', fn ($v) => (float) $v === 8.0)
        ->assertJsonCount(1, 'months') // nothing before the hire month
        ->json();

    $oct = $res['months'][0];
    expect($oct['on_time'])->toBe(9)
        ->and($oct['late'])->toBe(2)
        ->and($oct['on_leave'])->toBe(1)
        ->and($oct['absent'])->toBe(0)
        ->and($oct['working_days'])->toBe(11) // leave doesn't count against you
        ->and($oct['rate'])->toBe(100)
        ->and($oct['tardiness_minutes'])->toBe(20);
});

test('a missing past weekday counts as absent and breaks the on-time streak', function () {
    dtr($this->employee, '2026-10-14', 'present', ['morning_in' => '07:55:00']);
    // Oct 15 has no record at all -> absent
    dtr($this->employee, '2026-10-16', 'present', ['morning_in' => '07:50:00']);

    $res = $this->actingAs($this->employee, 'sanctum')->getJson('/api/me/attendance-summary')->json();

    expect($res['streak']['current'])->toBe(1)
        ->and($res['months'][0]['absent'])->toBeGreaterThanOrEqual(1);
});

test('weekends and leave do not break the streak', function () {
    dtr($this->employee, '2026-10-08', 'present', ['morning_in' => '07:55:00']); // Thu
    dtr($this->employee, '2026-10-09', 'present', ['morning_in' => '07:55:00']); // Fri
    // Sat/Sun 10–11: weekend
    dtr($this->employee, '2026-10-12', 'on_leave');                              // Mon
    dtr($this->employee, '2026-10-13', 'present', ['morning_in' => '07:55:00']); // Tue

    $streak = $this->actingAs($this->employee, 'sanctum')->getJson('/api/me/attendance-summary')->json('streak');

    expect($streak['best'])->toBeGreaterThanOrEqual(3);
});

test('the weekday pattern reports late counts and average time-in', function () {
    dtr($this->employee, '2026-10-05', 'late',    ['morning_in' => '08:30:00']); // Mon
    dtr($this->employee, '2026-10-12', 'present', ['morning_in' => '07:50:00']); // Mon

    $monday = collect(
        $this->actingAs($this->employee, 'sanctum')->getJson('/api/me/attendance-summary')->json('weekdays')
    )->firstWhere('label', 'Mon');

    expect($monday['worked'])->toBe(2)
        ->and($monday['late'])->toBe(1)
        ->and($monday['avg_arrival'])->toBe(490); // avg of 8:30 and 7:50 = 8:10
});

test('the summary only ever describes the signed-in user', function () {
    $coworker = User::factory()->create(['campus_id' => $this->campus->id, 'date_hired' => '2026-10-01']);
    dtr($coworker, '2026-10-05', 'late', ['morning_in' => '09:00:00']);

    $mon = collect(
        $this->actingAs($this->employee, 'sanctum')->getJson('/api/me/attendance-summary')->json('weekdays')
    )->firstWhere('label', 'Mon');

    expect($mon['late'])->toBe(0);
});

test('today\'s dashboard row includes all four raw punches', function () {
    dtr($this->employee, '2026-10-16', 'present', [
        'morning_in' => '07:55:00', 'morning_out' => '12:01:00', 'afternoon_in' => '12:58:00',
    ]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/attendance/today?date=2026-10-16')
        ->assertOk()
        ->assertJsonPath('rows.0.morning_out', '12:01:00')
        ->assertJsonPath('rows.0.afternoon_in', '12:58:00')
        ->assertJsonPath('rows.0.afternoon_out', null);
});

test('the months parameter is validated', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/me/attendance-summary?months=40')
        ->assertUnprocessable();
});

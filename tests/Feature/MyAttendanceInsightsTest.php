<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['code' => 'CAN']);
    $this->employee = User::factory()->create(['campus_id' => $this->campus->id]);
});

test('a weekend with no punches shows as weekend on the dashboard, not absent', function () {
    $sunday = Carbon::parse('2026-10-18'); // a Sunday
    $hr     = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $response = $this->actingAs($hr, 'sanctum')
        ->getJson("/api/attendance/today?date={$sunday->toDateString()}")
        ->assertOk();

    expect(collect($response->json('rows'))->pluck('status')->unique()->values()->all())->toBe(['weekend'])
        ->and($response->json('summary.absent'))->toBe(0);
});

test('a weekday with no punches still shows as absent', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/attendance/today?date=2026-10-19') // a Monday
        ->assertOk()
        ->assertJsonPath('rows.0.status', 'absent');
});

test('an employee can load their own month for the insights card', function () {
    DailyDtr::create([
        'employee_id'       => $this->employee->id,
        'work_date'         => '2026-10-05',
        'morning_in'        => '08:20:00',
        'status'            => 'late',
        'tardiness_minutes' => 5,
        'hours_rendered'    => 7.5,
    ]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/history/{$this->employee->id}?month=10&year=2026")
        ->assertOk()
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.status', 'late');
});

test('the month history fills in declared holidays so they are not shown as absent', function () {
    Holiday::create(['date' => '2026-10-07', 'name' => 'Founding Anniversary', 'type' => 'regular']);

    $records = $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/history/{$this->employee->id}?month=10&year=2026")
        ->assertOk()
        ->json('records');

    expect(collect($records)->firstWhere('status', 'holiday'))->not->toBeNull();
});

test('an employee cannot load a coworker\'s month', function () {
    $coworker = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/history/{$coworker->id}?month=10&year=2026")
        ->assertForbidden();
});

test('the month history rejects an invalid month', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/history/{$this->employee->id}?month=13&year=2026")
        ->assertUnprocessable();
});

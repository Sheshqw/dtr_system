<?php

use App\Models\AttendanceLog;
use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\FeaturePermission;
use App\Models\User;

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['code' => 'CAN']);
    $this->employee = User::factory()->create(['campus_id' => $this->campus->id]);
    $this->coworker = User::factory()->create(['campus_id' => $this->campus->id]);
    $this->today    = now()->toDateString();

    foreach ([$this->employee, $this->coworker] as $person) {
        AttendanceLog::create([
            'employee_id' => $person->id,
            'punch_time'  => now()->setTime(8, 0),
            'punch_type'  => 'IN',
            'source'      => 'biometric',
        ]);
        DailyDtr::create([
            'employee_id' => $person->id,
            'work_date'   => $this->today,
            'status'      => 'present',
        ]);
    }
});

test('faculty and staff see only their own row on the dashboard', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', $this->employee->id);
});

test('faculty and staff see only their own punch logs and daily records', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/logs?date={$this->today}")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.employee_id', $this->employee->employee_id);

    // Asking for a coworker by id still returns nothing of theirs.
    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/logs?date={$this->today}&employee_id={$this->coworker->id}")
        ->assertOk()
        ->assertJsonCount(0);

    $month = now()->month;
    $year  = now()->year;
    $daily = $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/attendance/daily?month={$month}&year={$year}")
        ->assertOk()
        ->json();

    expect(collect($daily['data'] ?? $daily)->pluck('employee_id')->unique()->values()->all())
        ->toBe([$this->employee->employee_id]);
});

test('faculty and staff cannot load the campus-wide dashboard charts', function () {
    foreach (['/api/analytics/trend', '/api/analytics/departments', '/api/analytics/staff-types'] as $url) {
        $this->actingAs($this->employee, 'sanctum')->getJson($url)->assertForbidden();
    }
});

test('hr officers still see the whole campus on the dashboard and charts', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($hr, 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk()
        ->assertJsonCount(3, 'rows');

    $this->actingAs($hr, 'sanctum')->getJson('/api/analytics/trend')->assertOk();
});

test('an employee granted "view other employees" in access control sees the campus again', function () {
    FeaturePermission::create([
        'user_id'     => $this->employee->id,
        'feature_key' => 'dtr_records.view_all',
        'granted'     => true,
    ]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk()
        ->assertJsonCount(2, 'rows');
});

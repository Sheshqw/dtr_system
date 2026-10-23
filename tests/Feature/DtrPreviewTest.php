<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\User;

test('the DTR preview carries each day\'s tardiness, undertime and overtime, and the campus director', function () {
    $campus   = Campus::factory()->create(['code' => 'CAN', 'director_name' => 'JUANCHO A. INTANO, Ph.D.']);
    $employee = User::factory()->create(['campus_id' => $campus->id]);

    DailyDtr::create([
        'employee_id' => $employee->id, 'work_date' => '2026-09-23', 'status' => 'late',
        'morning_in' => '07:39:00', 'morning_out' => '11:43:00', 'afternoon_in' => '12:55:00', 'afternoon_out' => '17:11:00',
        'hours_rendered' => 8.35, 'tardiness_minutes' => 39, 'undertime_minutes' => 0, 'overtime_minutes' => 20,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson("/api/reports/preview/{$employee->id}?month=9&year=2026")
        ->assertOk()
        ->assertJsonPath('employee.campus_director', 'JUANCHO A. INTANO, Ph.D.')
        ->assertJsonPath('records.0.tardiness_minutes', 39)
        ->assertJsonPath('records.0.undertime_minutes', 0)
        ->assertJsonPath('records.0.overtime_minutes', 20);
});

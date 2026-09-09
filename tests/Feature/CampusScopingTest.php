<?php

use App\Models\Campus;
use App\Models\DtrCorrection;
use App\Models\User;

beforeEach(function () {
    $this->campusA = Campus::factory()->create(['code' => 'CAN']);
    $this->campusB = Campus::factory()->create(['code' => 'TAN']);
});

// ── Employee management ─────────────────────────────────────────────────

test('an admin cannot update an employee from another campus', function () {
    $admin        = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $otherEmployee = User::factory()->create(['campus_id' => $this->campusB->id, 'first_name' => 'Original']);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/employees/{$otherEmployee->id}", ['first_name' => 'Hijacked'])
        ->assertStatus(403);

    expect($otherEmployee->fresh()->first_name)->toBe('Original');
});

test('an admin can update an employee within their own campus', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'first_name' => 'Original']);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['first_name' => 'Updated'])
        ->assertStatus(200);

    expect($employee->fresh()->first_name)->toBe('Updated');
});

test('a super admin can update an employee in any campus', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee   = User::factory()->create(['campus_id' => $this->campusB->id, 'first_name' => 'Original']);

    $this->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['first_name' => 'Updated'])
        ->assertStatus(200);

    expect($employee->fresh()->first_name)->toBe('Updated');
});

test('an hr officer cannot promote an employee to admin', function () {
    $hr       = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'role' => 'employee']);

    $this->actingAs($hr, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['role' => 'admin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role');

    expect($employee->fresh()->role)->toBe('employee');
});

test('an admin can promote an employee to admin within their own campus', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'role' => 'employee']);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['role' => 'admin'])
        ->assertStatus(200);

    expect($employee->fresh()->role)->toBe('admin');
});

// ── DTR corrections ──────────────────────────────────────────────────────

test('a reviewer cannot approve their own correction request', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);

    $correction = DtrCorrection::create([
        'employee_id'     => $hr->id,
        'work_date'       => now()->toDateString(),
        'field'           => 'morning_in',
        'requested_value' => '08:00',
        'reason'          => 'Forgot to punch in on time.',
        'status'          => 'pending',
    ]);

    $this->actingAs($hr, 'sanctum')
        ->patchJson("/api/dtr-corrections/{$correction->id}", ['status' => 'approved'])
        ->assertStatus(403);

    expect($correction->fresh()->status)->toBe('pending');
});

test('an admin cannot review a correction from another campus', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusB->id]);

    $correction = DtrCorrection::create([
        'employee_id'     => $employee->id,
        'work_date'       => now()->toDateString(),
        'field'           => 'morning_in',
        'requested_value' => '08:00',
        'reason'          => 'Forgot to punch in on time.',
        'status'          => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/dtr-corrections/{$correction->id}", ['status' => 'approved'])
        ->assertStatus(403);

    expect($correction->fresh()->status)->toBe('pending');
});

test('an admin can review a correction from their own campus', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    $correction = DtrCorrection::create([
        'employee_id'     => $employee->id,
        'work_date'       => now()->toDateString(),
        'field'           => 'morning_in',
        'requested_value' => '08:00',
        'reason'          => 'Forgot to punch in on time.',
        'status'          => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/dtr-corrections/{$correction->id}", ['status' => 'approved'])
        ->assertStatus(200);

    expect($correction->fresh()->status)->toBe('approved');
});

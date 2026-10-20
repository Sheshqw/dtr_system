<?php

use App\Models\Campus;
use App\Models\User;

beforeEach(function () {
    $this->campusA = Campus::factory()->create(['code' => 'CAN']);
    $this->campusB = Campus::factory()->create(['code' => 'TAN']);
});

// ── Employee management ─────────────────────────────────────────────────

test('an hr officer cannot update an employee from another campus', function () {
    $admin        = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
    $otherEmployee = User::factory()->create(['campus_id' => $this->campusB->id, 'first_name' => 'Original']);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/employees/{$otherEmployee->id}", ['first_name' => 'Hijacked'])
        ->assertStatus(403);

    expect($otherEmployee->fresh()->first_name)->toBe('Original');
});

test('an hr officer can update an employee within their own campus', function () {
    $admin    = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
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

test('an hr officer cannot promote an employee to super admin', function () {
    $hr       = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'role' => 'employee']);

    $this->actingAs($hr, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['role' => 'super_admin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role');

    expect($employee->fresh()->role)->toBe('employee');
});

test('an hr officer can make an employee an hr officer within their own campus', function () {
    $hr       = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'role' => 'employee']);

    $this->actingAs($hr, 'sanctum')
        ->putJson("/api/employees/{$employee->id}", ['role' => 'hr_officer'])
        ->assertStatus(200);

    expect($employee->fresh()->role)->toBe('hr_officer');
});

test('the removed admin and department head roles can no longer be assigned, even by a super admin', function () {
    $super    = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['campus_id' => $this->campusA->id, 'role' => 'employee']);

    foreach (['admin', 'department_head'] as $role) {
        $this->actingAs($super, 'sanctum')
            ->putJson("/api/employees/{$employee->id}", ['role' => $role])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    expect($employee->fresh()->role)->toBe('employee');
});

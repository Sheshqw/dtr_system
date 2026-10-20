<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function runRoleCleanupMigration(): void
{
    (require database_path('migrations/2026_10_20_000001_remove_admin_and_department_head_roles.php'))->up();
}

test('existing campus admins become hr officers and department heads become faculty / staff', function () {
    $campus  = Campus::factory()->create(['code' => 'CAN']);
    $admin   = User::factory()->create(['campus_id' => $campus->id, 'position' => 'Campus Administrator']);
    $head    = User::factory()->create(['campus_id' => $campus->id, 'position' => 'Dean']);
    $hr      = User::factory()->hrOfficer()->create(['campus_id' => $campus->id]);
    $super   = User::factory()->superAdmin()->create();
    $faculty = User::factory()->create(['campus_id' => $campus->id]);

    // The app can no longer assign the old roles, so set them directly.
    DB::table('employees')->where('id', $admin->id)->update(['role' => 'admin']);
    DB::table('employees')->where('id', $head->id)->update(['role' => 'department_head']);

    runRoleCleanupMigration();

    expect($admin->fresh()->role)->toBe('hr_officer')
        ->and($admin->fresh()->position)->toBeNull()   // seeded default cleared so it won't print on Form 48
        ->and($head->fresh()->role)->toBe('employee')
        ->and($head->fresh()->position)->toBe('Dean')    // a real position is kept
        ->and($hr->fresh()->role)->toBe('hr_officer')
        ->and($super->fresh()->role)->toBe('super_admin')
        ->and($faculty->fresh()->role)->toBe('employee');
});

test('a converted admin keeps a real position title', function () {
    $campus = Campus::factory()->create(['code' => 'TAN']);
    $admin  = User::factory()->create(['campus_id' => $campus->id, 'position' => 'HRMO III']);
    DB::table('employees')->where('id', $admin->id)->update(['role' => 'admin']);

    runRoleCleanupMigration();

    expect($admin->fresh()->role)->toBe('hr_officer')
        ->and($admin->fresh()->position)->toBe('HRMO III');
});

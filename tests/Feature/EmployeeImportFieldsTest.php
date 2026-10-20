<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->campus = Campus::factory()->create(['code' => 'CAN']);
});

function writeImportCsv(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import') . '.csv';
    $fh   = fopen($path, 'w');
    fputcsv($fh, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($fh, array_values($row));
    }
    fclose($fh);

    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

test('salary is visible to super admin and hr officer but hidden from an employee granted the roster', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id, 'salary' => 45000]);

    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super, 'sanctum')
        ->getJson("/api/employees?page=1")
        ->assertStatus(200)
        ->assertJsonFragment(['salary' => 45000.0]);

    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);
    $this->actingAs($hr, 'sanctum')
        ->getJson("/api/employees?page=1")
        ->assertStatus(200)
        ->assertJsonFragment(['salary' => 45000.0]);

    // A Faculty / Staff account the Super Admin granted "view other employees"
    // can open the roster, but never sees salaries.
    $viewer = User::factory()->create(['campus_id' => $this->campus->id]);
    \App\Models\FeaturePermission::create(['user_id' => $viewer->id, 'feature_key' => 'dtr_records.view_all', 'granted' => true]);
    $res = $this->actingAs($viewer, 'sanctum')
        ->getJson("/api/employees?page=1")
        ->assertStatus(200)
        ->json();

    $found = collect($res['data'])->firstWhere('id', $employee->id);
    expect($found['salary'])->toBeNull();
});

test('importing a row with gender, birthdate, salary, and designation creates the employee with those fields', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $file = writeImportCsv([[
        'Employee ID'       => 'EMP-TEST-001',
        'Last Name'         => 'Santos',
        'First Name'        => 'Maria',
        'Middle Name'       => '',
        'Gender'            => 'Female',
        'Birthdate'         => '1995-03-20',
        'Email'             => 'maria.santos.test@example.com',
        'Password'          => '',
        'Role'              => 'employee',
        'Campus'            => '',
        'Department'        => '',
        'Position'          => 'Instructor I',
        'Designation'       => 'Program Chair',
        'Monthly Salary'    => '32000.50',
        'Staff Type'        => 'faculty',
        'Employment Status' => 'permanent',
        'Date Hired'        => '2024-06-01',
    ]]);

    $this->actingAs($admin, 'sanctum')
        ->post('/api/employees/import', ['file' => $file])
        ->assertStatus(200)
        ->assertJson(['imported' => 1, 'failed' => 0]);

    $employee = User::where('employee_id', 'EMP-TEST-001')->first();
    expect($employee)->not->toBeNull();
    expect($employee->gender)->toBe('female');
    expect($employee->date_of_birth->toDateString())->toBe('1995-03-20');
    expect((float) $employee->salary)->toBe(32000.50);
    expect($employee->designation)->toBe('Program Chair');
});

test('importing a row with an invalid gender produces a clear per-row error', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $file = writeImportCsv([[
        'Employee ID' => 'EMP-TEST-002',
        'Last Name'   => 'Reyes',
        'First Name'  => 'Pedro',
        'Gender'      => 'Other',
    ]]);

    $res = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/employees/import/preview', ['file' => $file])
        ->assertStatus(200)
        ->json();

    expect($res['invalid_count'])->toBe(1);
    expect($res['rows'][0]['error'])->toContain('Gender');
});

test('importing a row with an invalid monthly salary produces a clear per-row error', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $file = writeImportCsv([[
        'Employee ID'    => 'EMP-TEST-003',
        'Last Name'      => 'Cruz',
        'First Name'     => 'Ana',
        'Monthly Salary' => 'not-a-number',
    ]]);

    $res = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/employees/import/preview', ['file' => $file])
        ->assertStatus(200)
        ->json();

    expect($res['invalid_count'])->toBe(1);
    expect($res['rows'][0]['error'])->toContain('Monthly Salary');
});

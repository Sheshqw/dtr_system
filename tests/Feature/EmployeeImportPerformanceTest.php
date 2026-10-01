<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('importing 1,000 employees in one request finishes well under a minute', function () {
    $campus = Campus::factory()->create(['code' => 'CAN']);
    $admin  = User::factory()->admin()->create(['campus_id' => $campus->id]);

    $path = tempnam(sys_get_temp_dir(), 'bulk_import') . '.csv';
    $fh   = fopen($path, 'w');
    fputcsv($fh, [
        'Employee ID', 'Last Name', 'First Name', 'Gender', 'Birthdate',
        'Staff Type', 'Employment Status', 'Monthly Salary', 'Designation',
    ]);
    for ($i = 1; $i <= 1000; $i++) {
        fputcsv($fh, [
            "BULK-{$i}", "LastName{$i}", "FirstName{$i}",
            $i % 2 === 0 ? 'Male' : 'Female',
            '1990-01-01',
            'faculty', 'permanent', '25000', '',
        ]);
    }
    fclose($fh);

    $file = new UploadedFile($path, 'bulk.csv', 'text/csv', null, true);

    $start = microtime(true);

    $res = $this->actingAs($admin, 'sanctum')
        ->post('/api/employees/import', ['file' => $file])
        ->assertStatus(200)
        ->json();

    $elapsed = microtime(true) - $start;

    expect($res['imported'])->toBe(1000);
    expect($res['failed'])->toBe(0);
    // Generous even for a slow CI box — the old per-row Hash::make() approach
    // measured ~460ms per row on this exact environment, i.e. ~7.5 minutes
    // for 1,000 rows alone; this is here to catch a regression back to that,
    // not to assert a tight production SLA.
    expect($elapsed)->toBeLessThan(30.0);

    expect(User::where('employee_id', 'like', 'BULK-%')->count())->toBe(1000);
})->group('performance');

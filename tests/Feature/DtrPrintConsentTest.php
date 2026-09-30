<?php

use App\Models\Campus;
use App\Models\DtrPrintConsent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->campusA = Campus::factory()->create(['code' => 'CAN']);
    $this->campusB = Campus::factory()->create(['code' => 'TAN']);
    $this->month   = (int) now()->format('n');
    $this->year    = (int) now()->format('Y');
});

test('an employee cannot download their DTR without an approved print consent', function () {
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson("/api/reports/form48/{$employee->id}?month={$this->month}&year={$this->year}")
        ->assertStatus(403);
});

test('an employee can download their DTR once a print consent is approved, and it is consumed after use', function () {
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    $consent = DtrPrintConsent::create([
        'employee_id' => $employee->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'approved',
    ]);

    $this->actingAs($employee, 'sanctum')
        ->get("/api/reports/form48/{$employee->id}?month={$this->month}&year={$this->year}")
        ->assertStatus(200)
        ->assertHeader('content-type', 'application/pdf');

    expect($consent->fresh()->used_at)->not->toBeNull();

    // The approval is single-use — a second download with the same consent fails.
    $this->actingAs($employee, 'sanctum')
        ->getJson("/api/reports/form48/{$employee->id}?month={$this->month}&year={$this->year}")
        ->assertStatus(403);
});

test('an employee cannot submit a duplicate pending print request for the same period', function () {
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    DtrPrintConsent::create([
        'employee_id' => $employee->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'pending',
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => $this->month, 'year' => $this->year])
        ->assertStatus(422);

    expect(DtrPrintConsent::where('employee_id', $employee->id)->count())->toBe(1);
});

test('a reviewer cannot approve their own print request', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);

    $consent = DtrPrintConsent::create([
        'employee_id' => $hr->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'pending',
    ]);

    $this->actingAs($hr, 'sanctum')
        ->patchJson("/api/dtr-print-consents/{$consent->id}", ['status' => 'approved'])
        ->assertStatus(403);

    expect($consent->fresh()->status)->toBe('pending');
});

test('an admin cannot review a print request from another campus', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusB->id]);

    $consent = DtrPrintConsent::create([
        'employee_id' => $employee->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/dtr-print-consents/{$consent->id}", ['status' => 'approved'])
        ->assertStatus(403);

    expect($consent->fresh()->status)->toBe('pending');
});

test('an admin can approve a print request from their own campus, letting the employee download', function () {
    $admin    = User::factory()->admin()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    $consent = DtrPrintConsent::create([
        'employee_id' => $employee->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/dtr-print-consents/{$consent->id}", ['status' => 'approved'])
        ->assertStatus(200);

    expect($consent->fresh()->status)->toBe('approved');

    $this->actingAs($employee, 'sanctum')
        ->get("/api/reports/form48/{$employee->id}?month={$this->month}&year={$this->year}")
        ->assertStatus(200);
});

test('a download renders fine when the approving reviewer has a signature on file', function () {
    $hr       = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
    $employee = User::factory()->create(['campus_id' => $this->campusA->id]);

    $signaturePath = UploadedFile::fake()->image('sig.png', 200, 80)->store('signatures', 'public');
    $hr->update(['signature_path' => $signaturePath, 'signature_enabled' => true]);

    DtrPrintConsent::create([
        'employee_id' => $employee->id,
        'month'       => $this->month,
        'year'        => $this->year,
        'status'      => 'approved',
        'reviewed_by' => $hr->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($employee, 'sanctum')
        ->get("/api/reports/form48/{$employee->id}?month={$this->month}&year={$this->year}")
        ->assertStatus(200)
        ->assertHeader('content-type', 'application/pdf');
});

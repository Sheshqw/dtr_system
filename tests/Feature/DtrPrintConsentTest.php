<?php

use App\Models\Campus;
use App\Models\DtrPrintConsent;
use App\Models\User;

beforeEach(function () {
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

test('an hr officer cannot review a print request from another campus', function () {
    $admin    = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
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

test('an hr officer can approve a print request from their own campus, letting the employee download', function () {
    $admin    = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);
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

test('the default queue view returns every pending request but caps reviewed history', function () {
    $admin = User::factory()->hrOfficer()->create(['campus_id' => $this->campusA->id]);

    // 3 pending requests (different employees, same period is fine since
    // each is a different employee) — all of these must come back.
    for ($i = 0; $i < 3; $i++) {
        $employee = User::factory()->create(['campus_id' => $this->campusA->id]);
        DtrPrintConsent::create([
            'employee_id' => $employee->id,
            'month'       => $this->month,
            'year'        => $this->year,
            'status'      => 'pending',
        ]);
    }

    // 15 already-reviewed requests — more than the history cap.
    for ($i = 0; $i < 15; $i++) {
        $employee = User::factory()->create(['campus_id' => $this->campusA->id]);
        DtrPrintConsent::create([
            'employee_id' => $employee->id,
            'month'       => $this->month,
            'year'        => $this->year,
            'status'      => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    $res = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/dtr-print-consents')
        ->assertStatus(200)
        ->json();

    $pendingCount = collect($res['consents'])->where('status', 'pending')->count();
    $approvedCount = collect($res['consents'])->where('status', 'approved')->count();

    expect($pendingCount)->toBe(3);
    expect($approvedCount)->toBe(10); // capped, not all 15
    expect($res['summary']['approved'])->toBe(15); // the summary count is still accurate
});

<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\DtrPrintConsent;
use App\Models\User;

beforeEach(function () {
    $campus = Campus::factory()->create(['code' => 'CAN']);

    $this->hr        = User::factory()->hrOfficer()->create(['campus_id' => $campus->id]);
    $this->cos       = User::factory()->create(['campus_id' => $campus->id, 'employment_status' => 'cos']);
    $this->jobOrder  = User::factory()->create(['campus_id' => $campus->id, 'employment_status' => 'job_order']);
    $this->permanent = User::factory()->create(['campus_id' => $campus->id, 'employment_status' => 'permanent']);

    // March 2026 — one record in each half.
    foreach ([$this->cos, $this->permanent] as $employee) {
        DailyDtr::create(['employee_id' => $employee->id, 'work_date' => '2026-03-05', 'status' => 'late',    'hours_rendered' => 8, 'tardiness_minutes' => 10]);
        DailyDtr::create(['employee_id' => $employee->id, 'work_date' => '2026-03-20', 'status' => 'present', 'hours_rendered' => 8, 'tardiness_minutes' => 20]);
    }
});

function dtrQuery(?string $period = null): string
{
    return 'month=3&year=2026' . ($period ? "&period={$period}" : '');
}

test('the 1st-half preview covers days 1-15 only', function () {
    $this->actingAs($this->hr, 'sanctum')
        ->getJson("/api/reports/preview/{$this->cos->id}?" . dtrQuery('first'))
        ->assertOk()
        ->assertJsonPath('period.start_day', 1)
        ->assertJsonPath('period.end_day', 15)
        ->assertJsonPath('month_label', 'March 1–15, 2026')
        ->assertJsonPath('totals.tardiness_minutes', 10)
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.day', 5);
});

test('the 2nd-half preview covers the 16th to month end', function () {
    $this->actingAs($this->hr, 'sanctum')
        ->getJson("/api/reports/preview/{$this->cos->id}?" . dtrQuery('second'))
        ->assertOk()
        ->assertJsonPath('period.start_day', 16)
        ->assertJsonPath('period.end_day', 31)
        ->assertJsonPath('month_label', 'March 16–31, 2026')
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.day', 20);
});

test('without a period the DTR still covers the whole month', function () {
    $this->actingAs($this->hr, 'sanctum')
        ->getJson("/api/reports/preview/{$this->cos->id}?" . dtrQuery())
        ->assertOk()
        ->assertJsonPath('period.key', 'full')
        ->assertJsonPath('month_label', 'March 2026')
        ->assertJsonPath('totals.tardiness_minutes', 30)
        ->assertJsonCount(2, 'records');
});

test('half-month DTRs are refused for permanent employees', function () {
    $this->actingAs($this->hr, 'sanctum')
        ->getJson("/api/reports/preview/{$this->permanent->id}?" . dtrQuery('first'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period');

    $this->actingAs($this->hr, 'sanctum')
        ->getJson("/api/reports/form48/{$this->permanent->id}?" . dtrQuery('second'))
        ->assertStatus(422);
});

test('the employee list says who prints semi-monthly', function () {
    $list = collect($this->actingAs($this->hr, 'sanctum')->getJson('/api/employees')->assertOk()->json())
        ->keyBy('id');

    expect($list[$this->cos->id]['semi_monthly_dtr'])->toBeTrue()
        ->and($list[$this->jobOrder->id]['semi_monthly_dtr'])->toBeTrue()
        ->and($list[$this->permanent->id]['semi_monthly_dtr'])->toBeFalse();
});

test('HR can download a half-month Form 48 PDF and CSV', function () {
    $this->actingAs($this->hr, 'sanctum')
        ->get("/api/reports/form48/{$this->cos->id}?" . dtrQuery('first'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload("DTR_{$this->cos->employee_id}_2026_3_1-15.pdf");

    $csv = $this->actingAs($this->hr, 'sanctum')
        ->get("/api/reports/export/{$this->cos->id}?" . dtrQuery('second'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('March 16–31, 2026')
        ->toContain('2026-03-20')
        ->not->toContain('2026-03-05');
});

test('a COS employee can request print permission for one half of the month', function () {
    $this->actingAs($this->cos, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => 3, 'year' => 2026, 'period' => 'first'])
        ->assertCreated()
        ->assertJsonPath('consent.period', 'first')
        ->assertJsonPath('consent.month_label', 'March 1–15, 2026');

    // A pending 1st-half request doesn't block asking for the 2nd half.
    $this->actingAs($this->cos, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => 3, 'year' => 2026, 'period' => 'second'])
        ->assertCreated();

    // ...but the same half twice is still a duplicate.
    $this->actingAs($this->cos, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => 3, 'year' => 2026, 'period' => 'first'])
        ->assertStatus(422);
});

test('a permanent employee cannot request a half-month print', function () {
    $this->actingAs($this->permanent, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => 3, 'year' => 2026, 'period' => 'first'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('period');
});

test('an approved 1st-half consent unlocks only the 1st half', function () {
    DtrPrintConsent::create([
        'employee_id' => $this->cos->id,
        'month'       => 3,
        'year'        => 2026,
        'period'      => 'first',
        'status'      => 'approved',
    ]);

    $this->actingAs($this->cos, 'sanctum')
        ->getJson("/api/reports/form48/{$this->cos->id}?" . dtrQuery('second'))
        ->assertStatus(403);

    $this->actingAs($this->cos, 'sanctum')
        ->getJson("/api/reports/form48/{$this->cos->id}?" . dtrQuery())
        ->assertStatus(403);

    $this->actingAs($this->cos, 'sanctum')
        ->get("/api/reports/form48/{$this->cos->id}?" . dtrQuery('first'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

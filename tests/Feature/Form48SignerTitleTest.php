<?php

use App\Models\Campus;
use App\Models\User;
use App\Support\DtrPeriod;

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['code' => 'CAN']);
    $this->employee = User::factory()->create(['campus_id' => $this->campus->id])->load('department', 'shift', 'campus');
});

function renderForm48(User $employee, ?User $reviewer): string
{
    return view('reports.form48', [
        'employee'        => $employee,
        'records'         => collect(),
        'period'          => new DtrPeriod(2026, 10),
        'year'            => 2026,
        'month'           => 10,
        'totals'          => ['days_present' => 0, 'tardiness_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0, 'hours_rendered' => 0],
        'signingReviewer' => $reviewer,
    ])->render();
}

function verifierTitle(string $html): string
{
    // The title line sits right after "Verified as to the prescribed office hours".
    preg_match('/Verified as to the prescribed office hours:.*?<div class="sig-sub"[^>]*>\s*(.*?)\s*<\/div>/s', $html, $m);

    return trim($m[1] ?? '');
}

test('with no approver the verifier title is HR Officer', function () {
    expect(verifierTitle(renderForm48($this->employee, null)))->toBe('HR Officer');
});

test('a super admin approver prints as HR Officer, even with a position on file', function () {
    $super = User::factory()->superAdmin()->create(['position' => 'ICT Director']);

    $html = renderForm48($this->employee, $super);

    expect(verifierTitle($html))->toBe('HR Officer')
        ->and($html)->toContain(strtoupper($super->full_name));
});

test('an hr officer approver shows their own position title when one is on file', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id, 'position' => 'HRMO III']);

    expect(verifierTitle(renderForm48($this->employee, $hr)))->toBe('HRMO III');
});

test('an hr officer approver without a position prints as HR Officer', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id, 'position' => null]);

    expect(verifierTitle(renderForm48($this->employee, $hr)))->toBe('HR Officer');
});

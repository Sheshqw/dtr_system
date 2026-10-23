<?php

use App\Models\Campus;
use App\Models\User;
use App\Support\DtrPeriod;

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['code' => 'CAN', 'director_name' => 'JUANCHO A. INTANO, Ph.D.']);
    $this->employee = User::factory()->create([
        'campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Reyes',
    ])->load('department', 'shift', 'campus');
});

function renderSheet(User $employee): string
{
    return view('reports.form48', ['title' => 'DTR', 'pages' => [[
        'employee' => $employee,
        'records'  => collect(),
        'period'   => new DtrPeriod(2026, 10),
        'year'     => 2026,
        'month'    => 10,
        'totals'   => ['days_present' => 0, 'tardiness_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0, 'hours_rendered' => 0],
        'holidays' => [],
    ]]])->render();
}

test('Form 48 is signed only by the employee and the campus director', function () {
    $html = renderSheet($this->employee);

    // Two copies per sheet.
    expect(substr_count($html, "Employee's Signature"))->toBe(2)
        ->and(substr_count($html, 'Campus Director'))->toBe(2)
        ->and(substr_count($html, 'JUANCHO A. INTANO, Ph.D.'))->toBe(2)
        ->and($html)->not->toContain('HR Officer');
});

test('the employee signs first, then the director verifies the office hours', function () {
    $html = renderSheet($this->employee);

    $employeeLine = strpos($html, "Employee's Signature");
    $verified     = strpos($html, 'Verified as to the prescribed office hours');
    $director     = strpos($html, 'Campus Director');

    expect($employeeLine)->toBeInt()->toBeLessThan($verified)
        ->and($verified)->toBeInt()->toBeLessThan($director)
        // The printed name sits on the employee's own signature line.
        ->and(substr_count($html, 'ANA REYES'))->toBeGreaterThanOrEqual(4);
});

test('a campus without a director on file leaves the name blank to sign by hand', function () {
    $this->campus->update(['director_name' => null]);

    $html = renderSheet($this->employee->fresh()->load('department', 'shift', 'campus'));

    expect(substr_count($html, 'Campus Director'))->toBe(2)
        ->and($html)->not->toContain('INTANO');
});

test('the super admin can set a campus director', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super, 'sanctum')
        ->putJson("/api/campuses/{$this->campus->id}", [
            'name' => $this->campus->name, 'code' => 'CAN', 'director_name' => 'MARIA L. CRUZ, Ed.D.',
        ])
        ->assertOk()
        ->assertJsonPath('director_name', 'MARIA L. CRUZ, Ed.D.');
});

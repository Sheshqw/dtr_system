<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use App\Support\DtrPeriod;

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['code' => 'CAN']);
    $this->employee = User::factory()->create(['campus_id' => $this->campus->id]);

    // Nov 1 2026 is a Sunday; Nov 2 and Nov 30 are Mondays.
    Holiday::create(['date' => '2026-11-01', 'name' => "All Saints' Day", 'type' => 'special']);
    Holiday::create(['date' => '2026-11-02', 'name' => "All Souls' Day",  'type' => 'special']);
    Holiday::create(['date' => '2026-11-30', 'name' => 'Bonifacio Day',   'type' => 'regular']);
});

test('a holiday label names the holiday and its type', function () {
    $h = Holiday::where('name', 'Bonifacio Day')->first();

    expect($h->label())->toBe('Bonifacio Day (Regular Holiday)');
});

test('the printed Form 48 shows each day\'s own holiday instead of a generic "Holiday"', function () {
    $html = view('reports.form48', [
        'employee'        => $this->employee->load('department', 'shift', 'campus'),
        'records'         => collect([
            2  => new DailyDtr(['work_date' => '2026-11-02', 'status' => 'holiday']),
            30 => new DailyDtr(['work_date' => '2026-11-30', 'status' => 'holiday']),
        ]),
        'period'          => new DtrPeriod(2026, 11),
        'year'            => 2026,
        'month'           => 11,
        'totals'          => ['days_present' => 0, 'tardiness_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0, 'hours_rendered' => 0],
        'signingReviewer' => null,
        'holidays'        => Holiday::keyedBetween('2026-11-01', '2026-11-30'),
    ])->render();

    // Two copies per page, so each label appears twice.
    expect(substr_count($html, 'All Souls&#039; Day (Special Holiday)'))->toBe(2)
        ->and(substr_count($html, 'Bonifacio Day (Regular Holiday)'))->toBe(2)
        // A holiday on a weekend still shows its name on that row.
        ->and(substr_count($html, 'All Saints&#039; Day (Special Holiday)'))->toBe(2);
});

test('the DTR preview sends every holiday in the period, weekend ones included', function () {
    $this->actingAs($this->employee, 'sanctum')
        ->getJson("/api/reports/preview/{$this->employee->id}?month=11&year=2026")
        ->assertOk()
        ->assertJsonPath('holidays', [
            ['day' => 1,  'label' => "All Saints' Day (Special Holiday)"],
            ['day' => 2,  'label' => "All Souls' Day (Special Holiday)"],
            ['day' => 30, 'label' => 'Bonifacio Day (Regular Holiday)'],
        ]);
});

test('a half-month preview only lists the holidays in that half', function () {
    $cos = User::factory()->create(['campus_id' => $this->campus->id, 'employment_status' => 'cos']);

    $this->actingAs($cos, 'sanctum')
        ->getJson("/api/reports/preview/{$cos->id}?month=11&year=2026&period=second")
        ->assertOk()
        ->assertJsonPath('holidays', [['day' => 30, 'label' => 'Bonifacio Day (Regular Holiday)']]);
});

test('the DTR CSV export writes the holiday name in the status column', function () {
    $hr = User::factory()->hrOfficer()->create(['campus_id' => $this->campus->id]);

    $csv = $this->actingAs($hr, 'sanctum')
        ->get("/api/reports/export/{$this->employee->id}?month=11&year=2026")
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain("All Souls' Day (Special Holiday)")
        ->and($csv)->toContain('Bonifacio Day (Regular Holiday)');
});

test('days without punches get no ABS remark on the printed Form 48', function () {
    $html = view('reports.form48', [
        'employee'        => $this->employee->load('department', 'shift', 'campus'),
        'records'         => collect([3 => new DailyDtr(['work_date' => '2026-11-03', 'status' => 'absent'])]),
        'period'          => new DtrPeriod(2026, 11),
        'year'            => 2026,
        'month'           => 11,
        'totals'          => ['days_present' => 0, 'tardiness_minutes' => 0, 'undertime_minutes' => 0, 'overtime_minutes' => 0, 'hours_rendered' => 0],
        'signingReviewer' => null,
        'holidays'        => Holiday::keyedBetween('2026-11-01', '2026-11-30'),
    ])->render();

    expect($html)->not->toContain('>ABS<')
        ->and($html)->toContain('HOL')
        ->and($html)->toContain('SUN');
});

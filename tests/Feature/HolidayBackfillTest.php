<?php

use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;

// A fixed, guaranteed weekday so it never accidentally lands on a weekend.
function nextWeekday(): string
{
    $date = Carbon::now()->next(Carbon::MONDAY);
    return $date->toDateString();
}

test('an employee created after a holiday was declared still sees it as Holiday when previewing their DTR', function () {
    $campus = Campus::factory()->create(['code' => 'CAN']);
    $holidayDate = nextWeekday();

    // Holiday declared first — HolidayController::markHolidayForAllEmployees()
    // only reaches employees who already existed at that moment.
    Holiday::create(['date' => $holidayDate, 'name' => 'Test Holiday', 'type' => 'regular']);

    // Employee created afterward — never in that original backfill pass.
    $employee = User::factory()->create(['campus_id' => $campus->id]);

    expect(DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $holidayDate)->exists())
        ->toBeFalse();

    $month = (int) Carbon::parse($holidayDate)->format('n');
    $year  = (int) Carbon::parse($holidayDate)->format('Y');

    $res = $this->actingAs($employee, 'sanctum')
        ->getJson("/api/reports/preview/{$employee->id}?month={$month}&year={$year}")
        ->assertStatus(200)
        ->json();

    $day = (int) Carbon::parse($holidayDate)->format('j');
    $record = collect($res['records'])->firstWhere('day', $day);

    expect($record)->not->toBeNull();
    expect($record['status'])->toBe('holiday');

    // The row now actually exists in the database too — every other place
    // that reads daily_dtr directly (exports, Attendance, Dashboard) is
    // correct from here on, not just this one response.
    expect(DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $holidayDate)->first()?->status)
        ->toBe('holiday');
});

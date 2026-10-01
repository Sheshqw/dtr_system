<?php

use App\Models\AttendanceLog;
use App\Models\Campus;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->campus = Campus::factory()->create(['code' => 'CAN']);
    // A deterministic, always-in-the-future Monday — never a weekend, never
    // "today" (so it can't collide with anything the app computes live).
    $this->weekday = Carbon::parse('next monday')->toDateString();
});

test('an employee can mark their own working day as on leave', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$this->weekday]])
        ->assertStatus(200)
        ->assertJson(['marked' => 1, 'failed' => 0]);

    $dtr = DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $this->weekday)->first();
    expect($dtr)->not->toBeNull();
    expect($dtr->status)->toBe('on_leave');
});

test('an employee can unmark a day they previously marked as on leave', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$this->weekday]])
        ->assertStatus(200);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave/unmark', ['dates' => [$this->weekday]])
        ->assertStatus(200)
        ->assertJson(['unmarked' => 1, 'failed' => 0]);

    $dtr = DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $this->weekday)->first();
    expect($dtr->status)->toBe('absent'); // no punches, so it recomputes back to absent
});

test('marking only ever affects the acting user, regardless of what else is in the request', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);
    $other    = User::factory()->create(['campus_id' => $this->campus->id]);

    // There is no employee_id field on this endpoint at all — this confirms
    // smuggling one in has no effect, since the controller never reads it.
    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$this->weekday], 'employee_id' => $other->id])
        ->assertStatus(200);

    expect(DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $this->weekday)->first()?->status)->toBe('on_leave');
    expect(DailyDtr::where('employee_id', $other->id)->whereDate('work_date', $this->weekday)->exists())->toBeFalse();
});

test('a day with existing time entries cannot be marked as on leave', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    AttendanceLog::create([
        'employee_id' => $employee->id,
        'punch_time'  => "{$this->weekday} 08:00:00",
        'punch_type'  => 'IN',
        'source'      => 'manual',
    ]);

    $res = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$this->weekday]])
        ->assertStatus(200)
        ->json();

    expect($res['marked'])->toBe(0);
    expect($res['failed'])->toBe(1);
    expect($res['results'][0]['error'])->toContain('time entries');
});

test('a weekend cannot be marked as on leave', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);
    $saturday = Carbon::parse('next saturday')->toDateString();

    $res = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$saturday]])
        ->assertStatus(200)
        ->json();

    expect($res['marked'])->toBe(0);
    expect($res['results'][0]['error'])->toContain('Weekend');
});

test('a declared holiday cannot be marked as on leave', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    Holiday::create(['name' => 'Test Holiday', 'date' => $this->weekday, 'type' => 'regular']);

    $res = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave', ['dates' => [$this->weekday]])
        ->assertStatus(200)
        ->json();

    expect($res['marked'])->toBe(0);
    expect($res['results'][0]['error'])->toContain('holiday');
});

test('unmarking a day that was never marked as on leave fails', function () {
    $employee = User::factory()->create(['campus_id' => $this->campus->id]);

    $res = $this->actingAs($employee, 'sanctum')
        ->postJson('/api/attendance/on-leave/unmark', ['dates' => [$this->weekday]])
        ->assertStatus(200)
        ->json();

    expect($res['unmarked'])->toBe(0);
    expect($res['failed'])->toBe(1);
});

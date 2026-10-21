<?php

use App\Models\Campus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

afterEach(fn () => Carbon::setTestNow());

test('the app runs on Philippine time', function () {
    expect(config('app.timezone'))->toBe('Asia/Manila')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Manila');
});

test('a print request shows the Philippine wall-clock time it was submitted', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-21 14:05:00', 'Asia/Manila'));
    $employee = User::factory()->create(['campus_id' => Campus::factory()->create()->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/dtr-print-consents', ['month' => 10, 'year' => 2026])
        ->assertCreated()
        ->assertJsonPath('consent.submitted_at', 'Oct 21, 2026 02:05 PM');
});

test('the nightly backup runs at 2 AM Philippine time, not 10 AM', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'auto-backup');

    Carbon::setTestNow(Carbon::parse('2026-10-22 02:00:00', 'Asia/Manila'));
    expect($event->isDue(app()))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'Asia/Manila'));
    expect($event->isDue(app()))->toBeFalse();
});

test('the migration moves saved UTC timestamps to Philippine time but never punch times', function () {
    $employee = User::factory()->create();
    DB::table('employees')->where('id', $employee->id)->update(['created_at' => '2026-10-21 06:05:00', 'locked_until' => null]);
    DB::table('attendance_logs')->insert([
        'employee_id' => $employee->id, 'punch_time' => '2026-10-21 07:58:00', 'punch_type' => 'IN',
        'source' => 'biometric', 'created_at' => '2026-10-20 23:58:05', 'updated_at' => '2026-10-20 23:58:05',
    ]);
    DB::table('daily_dtr')->insert([
        'employee_id' => $employee->id, 'work_date' => '2026-10-21', 'morning_in' => '07:58:00', 'status' => 'present',
        'created_at' => '2026-10-20 23:58:06', 'updated_at' => '2026-10-20 23:58:06',
    ]);

    $migration = require database_path('migrations/2026_10_21_000001_convert_stored_timestamps_to_philippine_time.php');
    $migration->up();

    $log = DB::table('attendance_logs')->first();
    $dtr = DB::table('daily_dtr')->first();
    $emp = DB::table('employees')->where('id', $employee->id)->first();

    expect($emp->created_at)->toBe('2026-10-21 14:05:00')
        ->and($emp->locked_until)->toBeNull()
        // Saved at 07:58 PH time as 23:58 UTC the night before — now agrees with the punch.
        ->and($log->created_at)->toBe('2026-10-21 07:58:05')
        ->and($log->punch_time)->toBe('2026-10-21 07:58:00')
        ->and($dtr->morning_in)->toBe('07:58:00')
        ->and(substr($dtr->work_date, 0, 10))->toBe('2026-10-21');

    $migration->down();

    expect(DB::table('attendance_logs')->value('created_at'))->toBe('2026-10-20 23:58:05')
        ->and(DB::table('attendance_logs')->value('punch_time'))->toBe('2026-10-21 07:58:00');
});

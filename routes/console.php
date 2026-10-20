<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTE: biometric logs now arrive via ADMS push (BiometricPushController) —
// the device dials out to us, so there's nothing to poll for over the
// internet. `php artisan biometric:sync` still exists for the admin's
// manual "Sync Now" button, used only when the server and device share a
// LAN; it's deliberately not scheduled here anymore, since a cloud-hosted
// server can never reach the device's local IP to begin with.

// Automated daily backup at 2:00 AM — keeps last 14 backups per campus
Schedule::call(function () {
    // One backup per campus that has an HR Officer, plus one all-campus backup.
    $admins = User::whereIn('role', ['super_admin', 'hr_officer'])->get();

    // Group by campus so each campus gets its own backup file
    $done = [];
    foreach ($admins as $admin) {
        $key = $admin->campus_id ?? 'all';
        if (isset($done[$key])) continue;
        $done[$key] = true;

        $campus   = strtolower($admin->campus?->code ?? 'all');
        $ts       = Carbon::now()->format('Ymd_His');
        $filename = "backup_{$campus}_{$ts}_auto.sql";

        try {
            Storage::disk('local')->makeDirectory('backups');

            // Reuse BackupController's dump logic via app() binding
            [$sql, $tableCounts] = app(\App\Http\Controllers\BackupController::class)
                ->generateDumpPublic($admin);

            Storage::disk('local')->put('backups/' . $filename, $sql);

            $meta = [
                'filename'      => $filename,
                'campus'        => $admin->campus?->name ?? 'All Campuses',
                'campus_code'   => $admin->campus?->code ?? 'ALL',
                'created_by'    => 'System (Scheduled)',
                'created_by_id' => 0,
                'role'          => 'scheduler',
                'total_records' => array_sum($tableCounts),
                'table_counts'  => $tableCounts,
                'db_driver'     => DB::getDriverName(),
                'generated_at'  => now()->toIso8601String(),
            ];

            Storage::disk('local')->put(
                'backups/' . str_replace('.sql', '.json', $filename),
                json_encode($meta, JSON_PRETTY_PRINT)
            );

            // Keep only 14 most recent auto backups per campus
            $pattern = "backup_{$campus}_";
            $autoFiles = collect(Storage::disk('local')->files('backups'))
                ->filter(fn($f) => str_contains($f, $pattern) && str_ends_with($f, '_auto.sql'))
                ->sortDesc()
                ->values();

            foreach ($autoFiles->slice(14) as $old) {
                Storage::disk('local')->delete($old);
                Storage::disk('local')->delete(str_replace('.sql', '.json', $old));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Auto-backup failed for campus {$campus}: " . $e->getMessage());
        }
    }
})->dailyAt('02:00')->name('auto-backup')->withoutOverlapping();

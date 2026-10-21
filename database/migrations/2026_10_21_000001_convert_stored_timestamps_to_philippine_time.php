<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app used to run on UTC, so every timestamp Laravel wrote itself
 * (created_at, reviewed_at, locked_until, …) was saved 8 hours behind
 * Philippine time. Now that config/app.php is Asia/Manila, those saved values
 * would read as Manila time and show 8 hours early — this moves them forward
 * once so old and new records agree.
 *
 * Deliberately NOT touched — these were always saved as local time:
 *   - attendance_logs.punch_time: the biometric device pushes its own local
 *     clock, and manual punches are typed in local time.
 *   - daily_dtr's time/date columns, holidays.date, and every other date-only
 *     or time-only column.
 *   - integer epoch columns (sessions, cache, jobs), which have no timezone.
 */
return new class extends Migration
{
    private const HOURS = 8;

    private const COLUMNS = [
        'departments'            => ['created_at', 'updated_at'],
        'shifts'                 => ['created_at', 'updated_at'],
        'employees'              => ['locked_until', 'created_at', 'updated_at'],
        'password_reset_tokens'  => ['created_at'],
        'failed_jobs'            => ['failed_at'],
        'personal_access_tokens' => ['last_used_at', 'expires_at', 'created_at', 'updated_at'],
        'attendance_logs'        => ['created_at', 'updated_at'],
        'daily_dtr'              => ['corrected_at', 'created_at', 'updated_at'],
        'holidays'               => ['created_at', 'updated_at'],
        'audit_logs'             => ['created_at', 'updated_at'],
        'campuses'               => ['created_at', 'updated_at'],
        'system_health_logs'     => ['last_seen_at', 'acknowledged_at', 'resolved_at', 'created_at', 'updated_at'],
        'system_settings'        => ['created_at', 'updated_at'],
        'feature_permissions'    => ['created_at', 'updated_at'],
        'dtr_print_consents'     => ['reviewed_at', 'used_at', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        DB::transaction(function () use ($hours) {
            foreach (self::COLUMNS as $table => $columns) {
                if (! Schema::hasTable($table)) continue;

                $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
                if (! $columns) continue;

                // NULLs stay NULL in every dialect below, so one UPDATE per
                // table covers all of its columns at once.
                $sets = implode(', ', array_map(fn ($c) => "{$c} = " . $this->plus($c, $hours), $columns));

                DB::statement("UPDATE {$table} SET {$sets}");
            }
        });
    }

    private function plus(string $column, int $hours): string
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => "DATE_ADD({$column}, INTERVAL {$hours} HOUR)",
            'sqlite'           => "datetime({$column}, '" . sprintf('%+d', $hours) . " hours')",
            'pgsql'            => "{$column} + interval '{$hours} hours'",
            default            => throw new RuntimeException('Unsupported database driver for the timezone migration.'),
        };
    }
};

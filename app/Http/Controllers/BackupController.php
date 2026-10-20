<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    use ScopesByCampus;

    // Tables backed up in dependency order (parent tables first)
    private const TABLES = [
        'campuses',
        'departments',
        'shifts',
        'employees',
        'holidays',
        'attendance_logs',
        'daily_dtr',
        'audit_logs',
    ];

    // ── Public Endpoints ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->gate($request->user());
        $user = $request->user();

        Storage::disk('local')->makeDirectory('backups');

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn($f) => str_ends_with($f, '.sql'))
            ->filter(fn($f) => $this->isSuperAdmin($request) || $this->backupBelongsToUser(basename($f), $user))
            ->map(function ($f) {
                $name     = basename($f);
                $metaPath = 'backups/' . str_replace('.sql', '.json', $name);
                $meta     = Storage::disk('local')->exists($metaPath)
                    ? json_decode(Storage::disk('local')->get($metaPath), true)
                    : [];

                return [
                    'filename'      => $name,
                    'size'          => $this->formatBytes(Storage::disk('local')->size($f)),
                    'size_bytes'    => Storage::disk('local')->size($f),
                    'created_at'    => Carbon::createFromTimestamp(
                        Storage::disk('local')->lastModified($f)
                    )->format('Y-m-d H:i:s'),
                    'campus'        => $meta['campus']       ?? 'Unknown',
                    'created_by'    => $meta['created_by']   ?? 'Unknown',
                    'total_records' => $meta['total_records'] ?? null,
                    'table_counts'  => $meta['table_counts']  ?? [],
                    'db_driver'     => $meta['db_driver']     ?? null,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        // Storage summary
        $totalBytes = $files->sum('size_bytes');
        $count      = $files->count();

        return response()->json([
            'backups' => $files,
            'summary' => [
                'total_files'   => $count,
                'total_size'    => $this->formatBytes($totalBytes),
                'total_bytes'   => $totalBytes,
                'last_backup'   => $files->first()['created_at'] ?? null,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $this->gate($request->user());

        $user     = $request->user();
        $campus   = strtolower($user->campus?->code ?? 'all');
        $ts       = Carbon::now()->format('Ymd_His');
        $filename = "backup_{$campus}_{$ts}.sql";

        [$sql, $tableCounts] = $this->generateDump($user);

        $totalRecords = array_sum($tableCounts);
        $path         = 'backups/' . $filename;

        Storage::disk('local')->put($path, $sql);

        // Save metadata sidecar
        $meta = [
            'filename'      => $filename,
            'campus'        => $user->campus?->name ?? 'All Campuses',
            'campus_code'   => $user->campus?->code ?? 'ALL',
            'created_by'    => $user->full_name . ' (' . $user->employee_id . ')',
            'created_by_id' => $user->id,
            'role'          => $user->role,
            'total_records' => $totalRecords,
            'table_counts'  => $tableCounts,
            'db_driver'     => DB::getDriverName(),
            'generated_at'  => now()->toIso8601String(),
        ];

        Storage::disk('local')->put(
            'backups/' . str_replace('.sql', '.json', $filename),
            json_encode($meta, JSON_PRETTY_PRINT)
        );

        return response()->json([
            'message'       => 'Backup created successfully.',
            'filename'      => $filename,
            'size'          => $this->formatBytes(strlen($sql)),
            'total_records' => $totalRecords,
            'table_counts'  => $tableCounts,
        ]);
    }

    public function download(Request $request, string $filename)
    {
        $this->gate($request->user());

        $safe = basename($filename);
        $path = 'backups/' . $safe;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        if (! $this->isSuperAdmin($request) && ! $this->backupBelongsToUser($safe, $request->user())) {
            abort(403, 'You do not have access to this campus\'s backup.');
        }

        return Storage::disk('local')->download($path, $safe);
    }

    public function restore(Request $request, string $filename)
    {
        $this->superAdminGate($request->user());

        $safe = basename($filename);
        $path = 'backups/' . $safe;

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        $sql = Storage::disk('local')->get($path);

        // Split into individual statements and execute
        DB::unprepared('PRAGMA foreign_keys = OFF;');

        try {
            $statements = array_filter(
                array_map('trim', explode(";\n", $sql)),
                fn($s) => $s !== '' && ! str_starts_with($s, '--')
            );

            $executed = 0;
            foreach ($statements as $statement) {
                if (trim($statement) === '') continue;
                DB::unprepared($statement . ';');
                $executed++;
            }

            DB::unprepared('PRAGMA foreign_keys = ON;');

            return response()->json([
                'message'    => 'Backup restored successfully.',
                'statements' => $executed,
            ]);
        } catch (\Exception $e) {
            DB::unprepared('PRAGMA foreign_keys = ON;');
            return response()->json([
                'message' => 'Restore failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, string $filename)
    {
        $this->superAdminGate($request->user());

        $safe = basename($filename);
        Storage::disk('local')->delete('backups/' . $safe);

        // Also delete the metadata sidecar if present
        $metaSafe = str_replace('.sql', '.json', $safe);
        Storage::disk('local')->delete('backups/' . $metaSafe);

        return response()->json(['message' => 'Backup deleted.']);
    }

    // ── SQL Dump Generator ────────────────────────────────────────────

    /** Public alias used by the scheduled auto-backup in routes/console.php */
    public function generateDumpPublic(\App\Models\User $user): array
    {
        return $this->generateDump($user);
    }

    private function generateDump(\App\Models\User $user): array
    {
        $campusId    = ($user->role !== 'super_admin') ? $user->campus_id : null;
        $tableCounts = [];
        $lines       = [];

        $lines[] = '-- ╔══════════════════════════════════════════════════════════════════╗';
        $lines[] = '-- ║         NEMSU UNIFIED DTR MANAGEMENT SYSTEM — SQL BACKUP         ║';
        $lines[] = '-- ╚══════════════════════════════════════════════════════════════════╝';
        $lines[] = '--';
        $lines[] = '-- System    : NEMSU Unified DTR Management System (UDTRMS)';
        $lines[] = '-- Generated : ' . now()->format('F j, Y  h:i:s A');
        $lines[] = '-- Campus    : ' . ($user->campus?->name ?? 'All Campuses');
        $lines[] = '-- Created By: ' . $user->full_name . ' (' . $user->employee_id . ')';
        $lines[] = '-- Role      : ' . strtoupper(str_replace('_', ' ', $user->role));
        $lines[] = '-- Database  : ' . strtoupper(DB::getDriverName());
        $lines[] = '--';
        $lines[] = '-- RESTORE INSTRUCTIONS:';
        $lines[] = '--   1. Open the Backup page and click "Restore" on this file.';
        $campusCode = $user->campus?->code ?? 'all';
        $lines[] = '--   2. Or run manually: sqlite3 database.sqlite < ' . "backup_{$campusCode}_*.sql";
        $lines[] = '--';
        $lines[] = '';

        if (DB::getDriverName() === 'mysql') {
            $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
            $lines[] = '';
        } else {
            $lines[] = 'PRAGMA foreign_keys = OFF;';
            $lines[] = '';
        }

        foreach (self::TABLES as $table) {
            try {
                $rows = $this->tableRows($table, $campusId);
                $count = $rows->count();
                $tableCounts[$table] = $count;

                if ($count === 0) {
                    $lines[] = "-- [{$table}] — 0 records (skipped)";
                    $lines[] = '';
                    continue;
                }

                $lines[] = str_repeat('-', 70);
                $lines[] = "-- TABLE: {$table}  ({$count} " . ($count === 1 ? 'record' : 'records') . ')';
                $lines[] = str_repeat('-', 70);

                $cols    = array_keys((array) $rows->first());
                $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

                foreach ($rows as $row) {
                    $vals    = implode(', ', array_map([$this, 'escapeValue'], (array) $row));
                    $lines[] = "INSERT INTO `{$table}` ({$colList}) VALUES ({$vals});";
                }

                $lines[] = '';
            } catch (\Exception $e) {
                $tableCounts[$table] = 0;
                $lines[] = "-- [{$table}] SKIPPED — " . $e->getMessage();
                $lines[] = '';
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        } else {
            $lines[] = 'PRAGMA foreign_keys = ON;';
        }

        $lines[] = '';
        $lines[] = '-- End of backup — Total tables: ' . count(self::TABLES) . ' — Total records: ' . array_sum($tableCounts);

        return [implode("\n", $lines), $tableCounts];
    }

    private function tableRows(string $table, ?int $campusId)
    {
        if ($campusId === null) {
            return DB::table($table)->get();
        }

        $campusEmployeeIds = DB::table('employees')
            ->where('campus_id', $campusId)
            ->pluck('id');

        return match ($table) {
            'campuses'        => DB::table($table)->where('id', $campusId)->get(),
            'departments'     => DB::table($table)->where('campus_id', $campusId)->get(),
            'employees'       => DB::table($table)->where('campus_id', $campusId)->get(),
            'attendance_logs' => DB::table($table)->whereIn('employee_id', $campusEmployeeIds)->get(),
            'daily_dtr'       => DB::table($table)->whereIn('employee_id', $campusEmployeeIds)->get(),
            'audit_logs'      => DB::table($table)->whereIn('user_id', $campusEmployeeIds)->get(),
            default           => DB::table($table)->get(), // shifts, holidays — system-wide
        };
    }

    private function escapeValue(mixed $v): string
    {
        if ($v === null)               return 'NULL';
        if (is_int($v) || is_float($v)) return (string) $v;
        if (is_bool($v))               return $v ? '1' : '0';
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)     return round($bytes / 1_024, 2) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Whether a campus-scoped admin/HR officer may see/download this backup file.
     * Checks the metadata sidecar's campus_code first, falling back to the
     * `backup_{code}_...` filename prefix if the sidecar is missing.
     */
    private function backupBelongsToUser(string $filename, \App\Models\User $user): bool
    {
        $userCode = strtolower($user->campus?->code ?? '');
        if ($userCode === '') {
            return false;
        }

        $metaPath = 'backups/' . str_replace('.sql', '.json', $filename);
        if (Storage::disk('local')->exists($metaPath)) {
            $meta = json_decode(Storage::disk('local')->get($metaPath), true);
            $code = strtolower($meta['campus_code'] ?? '');
            if ($code !== '') {
                return $code === $userCode;
            }
        }

        return str_starts_with(strtolower($filename), "backup_{$userCode}_");
    }

    /**
     * Who can list, create and download backups (of their own campus,
     * unless Super Admin). Restoring and deleting stay Super Admin only.
     */
    private function gate(\App\Models\User $user): void
    {
        if (! in_array($user->role, ['super_admin', 'admin', 'hr_officer'])) {
            abort(403, 'Only a Super Admin, Campus Admin, or HR Officer can manage backups.');
        }
    }

    private function superAdminGate(\App\Models\User $user): void
    {
        if ($user->role !== 'super_admin') {
            abort(403, 'Only Super Admin can perform this action.');
        }
    }
}

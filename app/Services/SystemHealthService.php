<?php

namespace App\Services;

use App\Models\Department;
use App\Models\SystemHealthLog;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    /**
     * Record (or bump) a health log entry. Recurrences of the same problem
     * (same type+message+file+line) increment `occurrences` on the existing
     * row instead of flooding the table with duplicates — unless that row
     * was already resolved, in which case it reopens as a fresh occurrence.
     */
    public function record(
        string $severity,
        string $type,
        string $message,
        ?string $file = null,
        ?int $line = null,
        ?string $trace = null,
        ?array $context = null,
    ): SystemHealthLog {
        $signature = hash('sha256', $type . '|' . $message . '|' . $file . '|' . $line);

        $existing = SystemHealthLog::where('signature', $signature)
            ->where('status', '!=', 'resolved')
            ->first();

        if ($existing) {
            $existing->update([
                'occurrences'  => $existing->occurrences + 1,
                'last_seen_at' => now(),
                'trace'        => $trace ?? $existing->trace,
                'context'      => $context ?? $existing->context,
            ]);
            return $existing;
        }

        return SystemHealthLog::create([
            'severity'     => $severity,
            'type'         => $type,
            'message'      => $message,
            'file'         => $file,
            'line'         => $line,
            'trace'        => $trace,
            'context'      => $context,
            'signature'    => $signature,
            'occurrences'  => 1,
            'last_seen_at' => now(),
            'status'       => 'new',
        ]);
    }

    /**
     * Run the system integrity scanner. Checks real data/relational health
     * across the DB and records a warning for each problem found. Returns
     * how many issues were found on this pass.
     */
    public function runScan(): int
    {
        $found = 0;

        $found += $this->checkOrphanedRecords('attendance_logs', 'employee_id', 'employees', 'Attendance log(s)');
        $found += $this->checkOrphanedRecords('daily_dtr', 'employee_id', 'employees', 'Daily DTR record(s)');
        $found += $this->checkDuplicateFingerprints();
        $found += $this->checkMissingGovernmentIds();
        $found += $this->checkEmployeesMissingShift();
        $found += $this->checkDepartmentsMissingHead();
        $found += $this->checkCampusesMissingAdmin();

        return $found;
    }

    private function checkOrphanedRecords(string $table, string $fk, string $refTable, string $label): int
    {
        $count = DB::table($table)
            ->whereNotIn($fk, DB::table($refTable)->select('id'))
            ->count();

        if ($count === 0) return 0;

        $this->record(
            severity: 'critical',
            type: 'db_integrity',
            message: "{$label} reference {$count} employee ID(s) that no longer exist. Data may have been deleted without cascading.",
            file: $table,
        );

        return 1;
    }

    private function checkDuplicateFingerprints(): int
    {
        $dupes = DB::table('employees')
            ->select('fingerprint_id', DB::raw('COUNT(*) as c'))
            ->where('is_active', true)
            ->whereNotNull('fingerprint_id')
            ->groupBy('fingerprint_id')
            ->having('c', '>', 1)
            ->get();

        if ($dupes->isEmpty()) return 0;

        foreach ($dupes as $dupe) {
            $this->record(
                severity: 'critical',
                type: 'data_integrity',
                message: "Biometric device PIN \"{$dupe->fingerprint_id}\" is assigned to {$dupe->c} active employees. Their punches may be credited to the wrong person.",
                file: 'employees.fingerprint_id',
            );
        }

        return $dupes->count();
    }

    private function checkMissingGovernmentIds(): int
    {
        $count = DB::table('employees')
            ->where('is_active', true)
            ->where('employment_status', 'permanent')
            ->where(fn($q) => $q
                ->whereNull('gsis_number')
                ->orWhereNull('philhealth_number')
                ->orWhereNull('pagibig_number')
                ->orWhereNull('tin_number')
            )
            ->count();

        if ($count === 0) return 0;

        $this->record(
            severity: 'warning',
            type: 'data_integrity',
            message: "{$count} active permanent employee(s) are missing one or more government ID numbers (GSIS/PhilHealth/Pag-IBIG/TIN). CSC Form 48 deduction reports will be incomplete for them.",
            file: 'employees',
        );

        return 1;
    }

    private function checkEmployeesMissingShift(): int
    {
        $count = DB::table('employees')->where('is_active', true)->whereNull('shift_id')->count();
        if ($count === 0) return 0;

        $this->record(
            severity: 'warning',
            type: 'data_integrity',
            message: "{$count} active employee(s) have no shift assigned. Their working hours cannot be computed correctly.",
            file: 'employees.shift_id',
        );

        return 1;
    }

    private function checkDepartmentsMissingHead(): int
    {
        $count = Department::whereNull('head_id')->count();
        if ($count === 0) return 0;

        $this->record(
            severity: 'info',
            type: 'data_integrity',
            message: "{$count} department(s) have no department head assigned.",
            file: 'departments.head_id',
        );

        return 1;
    }

    private function checkCampusesMissingAdmin(): int
    {
        $campusIds = DB::table('campuses')->pluck('id');
        $withAdmin = DB::table('employees')
            ->where('role', 'admin')
            ->where('is_active', true)
            ->distinct()
            ->pluck('campus_id');

        $missing = $campusIds->diff($withAdmin)->count();
        if ($missing === 0) return 0;

        $this->record(
            severity: 'warning',
            type: 'data_integrity',
            message: "{$missing} campus(es) have no active Campus Admin account assigned.",
            file: 'campuses',
        );

        return 1;
    }
}

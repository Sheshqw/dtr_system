<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\WorkingHoursService;
use App\Services\ZktecoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncBiometric extends Command
{
    protected $signature   = 'biometric:sync {--dry-run : Preview records without saving}';
    protected $description = 'Pull attendance logs from the biometric device and write to DTR';

    public function handle(ZktecoService $zk, WorkingHoursService $wh): int
    {
        $this->info("Connecting to {$zk->getIp()}:{$zk->getPort()} …");

        try {
            $logs = $zk->getAttendanceLogs();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($logs)) {
            $this->warn('No attendance logs returned from device.');
            return self::SUCCESS;
        }

        $this->info('Processing ' . count($logs) . ' log(s)…');

        $imported = 0;
        $skipped  = 0;
        $unknown  = 0;
        $affected = [];

        foreach ($logs as $log) {
            $fingerprintId = $log['id'];
            $timestamp     = $log['timestamp'];
            $state         = $log['state'];

            if (! $fingerprintId || ! $timestamp) {
                $skipped++;
                continue;
            }

            $employee = User::where('fingerprint_id', $fingerprintId)
                ->where('is_active', true)
                ->first();

            if (! $employee) {
                $unknown++;
                continue;
            }

            $punchTime = Carbon::parse($timestamp);
            $workDate  = $punchTime->toDateString();

            // Map device state to punch type; fall back to alternating IN/OUT
            $punchType = match ($state) {
                0       => 'IN',
                1       => 'OUT',
                default => null,
            };

            if (! $punchType) {
                $count     = AttendanceLog::where('employee_id', $employee->id)
                    ->whereDate('punch_time', $workDate)->count();
                $punchType = ($count % 2 === 0) ? 'IN' : 'OUT';
            }

            // 5-minute duplicate guard
            $duplicate = AttendanceLog::where('employee_id', $employee->id)
                ->whereBetween('punch_time', [
                    $punchTime->copy()->subMinutes(5),
                    $punchTime->copy()->addMinutes(5),
                ])->exists();

            if ($duplicate) {
                $skipped++;
                continue;
            }

            if (! $this->option('dry-run')) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'punch_time'  => $punchTime,
                    'punch_type'  => $punchType,
                    'source'      => 'biometric',
                ]);
                $affected[$employee->id][$workDate] = true;
            }

            $imported++;
        }

        // Recompute DTR for every affected employee-date pair
        if (! $this->option('dry-run')) {
            foreach ($affected as $empId => $dates) {
                $employee = User::find($empId);
                foreach (array_keys($dates) as $date) {
                    $wh->computeDailyDtr($employee, $date);
                }
            }
        }

        $this->table(['Result', 'Count'], [
            ['Imported', $imported],
            ['Skipped (duplicate)', $skipped],
            ['Unknown fingerprint ID', $unknown],
        ]);

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\AttendancePunched;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\WorkingHoursService;
use App\Services\ZktecoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BiometricController extends Controller
{
    public function __construct(
        private ZktecoService     $zk,
        private WorkingHoursService $wh,
    ) {}

    /**
     * Return device reachability status.
     * GET /api/biometric/status
     */
    public function debug(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'hr_officer'])) {
            abort(403, 'Unauthorized.');
        }

        return response()->json($this->zk->debugRawResponse());
    }

    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'hr_officer'])) {
            abort(403, 'Unauthorized.');
        }

        return response()->json([
            'ip'          => $this->zk->getIp(),
            'port'        => $this->zk->getPort(),
            'reachable'   => $this->zk->isReachable(),
            'last_synced' => cache('biometric_last_synced'),
        ]);
    }

    /**
     * Trigger a live sync from the device.
     * POST /api/biometric/sync
     */
    public function sync(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'hr_officer'])) {
            abort(403, 'Unauthorized.');
        }

        set_time_limit(120); // allow up to 2 minutes for device communication

        try {
            $logs = $this->zk->getAttendanceLogs();
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $result = $this->processLogs($logs, 'biometric');

        cache(['biometric_last_synced' => now()->format('Y-m-d H:i:s')], 3600);

        return response()->json($result);
    }

    /**
     * Import attendance from a file exported from the device (USB).
     * POST /api/biometric/import
     *
     * Supported formats (auto-detected):
     *   DAT  — tab-separated: UserID \t DateTime \t Status \t ...
     *   CSV  — comma-separated: UserID, DateTime, Status
     *   TXT  — space-separated: UserID DateTime Status
     */
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'hr_officer'])) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'file' => 'required|file|mimes:dat,csv,txt|max:10240',
        ]);

        $path    = $request->file('file')->getRealPath();
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            return response()->json(['error' => 'The uploaded file is empty or unreadable.'], 422);
        }

        $logs = $this->parseFile($content);

        if (empty($logs)) {
            return response()->json(['error' => 'No valid records found in the file. Check the format.'], 422);
        }

        // 'attendance_logs.source' only allows 'biometric' or 'manual' — a
        // file import is a manual action (a human uploaded it), so it maps
        // to 'manual', not a separate 'import' value the DB doesn't have.
        $result = $this->processLogs($logs, 'manual');

        return response()->json($result);
    }

    // -------------------------------------------------------------------------

    /**
     * Parse attendance file into a normalized log array.
     * Returns: [ ['id' => string, 'timestamp' => string, 'state' => int], ... ]
     */
    private function parseFile(string $content): array
    {
        $logs  = [];
        $lines = preg_split('/\r?\n/', trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            // Auto-detect delimiter
            if (substr_count($line, "\t") >= 1) {
                $cols = explode("\t", $line);
            } elseif (substr_count($line, ',') >= 1) {
                $cols = str_getcsv($line);
            } else {
                $cols = preg_split('/\s+/', $line, 5);
            }

            $cols = array_map('trim', $cols);

            // Need at least 2 columns: UserID + DateTime
            if (count($cols) < 2) continue;

            $userId = $cols[0];

            // Some files split date and time into two columns
            if (isset($cols[2]) && preg_match('/^\d{2}:\d{2}/', $cols[2])) {
                // Format: UserID | Date | Time | Status ...
                $datetime = $cols[1] . ' ' . $cols[2];
                // -1 sentinel means "no state column" → processLogs uses alternating IN/OUT
                $state    = isset($cols[3]) ? (int) $cols[3] : -1;
            } else {
                // Format: UserID | DateTime | Status ...
                $datetime = $cols[1];
                $state    = isset($cols[2]) ? (int) $cols[2] : -1;
            }

            // Validate we have a parseable date
            try {
                Carbon::parse($datetime);
            } catch (\Throwable) {
                continue;
            }

            $logs[] = [
                'id'        => $userId,
                'timestamp' => $datetime,
                'state'     => $state,
            ];
        }

        return $logs;
    }

    /**
     * Process a normalized log array into AttendanceLog records.
     */
    private function processLogs(array $logs, string $source): array
    {
        $imported = 0;
        $skipped  = 0;
        $unknown  = 0;
        $affected = [];
        $details  = [];

        foreach ($logs as $log) {
            $fingerprintId = trim((string) $log['id']);
            $timestamp     = $log['timestamp'];
            $state         = (int) ($log['state'] ?? 0);

            if (! $fingerprintId || ! $timestamp) { $skipped++; continue; }

            $employee = User::where('fingerprint_id', $fingerprintId)
                ->where('is_active', true)
                ->first();

            if (! $employee) {
                $unknown++;
                $details[] = ['fingerprint_id' => $fingerprintId, 'result' => 'unknown'];
                continue;
            }

            $punchTime = Carbon::parse($timestamp);
            $workDate  = $this->wh->resolveWorkDate($employee, $punchTime);

            $punchType = match ($state) {
                0       => 'IN',
                1       => 'OUT',
                default => null,
            };

            if (! $punchType) {
                $count     = AttendanceLog::where('employee_id', $employee->id)
                    ->whereDate('punch_time', $punchTime->toDateString())->count();
                $punchType = ($count % 2 === 0) ? 'IN' : 'OUT';
            }

            $created = $this->wh->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => $punchType,
                'source'     => $source,
            ]);

            if (! $created) {
                $skipped++;
                $details[] = [
                    'fingerprint_id' => $fingerprintId,
                    'name'           => $employee->full_name,
                    'time'           => $punchTime->format('Y-m-d H:i'),
                    'result'         => 'duplicate',
                ];
                continue;
            }

            if ($punchTime->isToday()) {
                broadcast(new AttendancePunched(
                    name:        $employee->full_name,
                    employee_id: $employee->employee_id,
                    punch_type:  $punchType,
                    time:        $punchTime->format('h:i A'),
                    campus_code: $employee->campus?->code,
                ));
            }

            $affected[$employee->id][$workDate] = $employee;
            $imported++;

            $details[] = [
                'fingerprint_id' => $fingerprintId,
                'name'           => $employee->full_name,
                'time'           => $punchTime->format('Y-m-d H:i'),
                'punch_type'     => $punchType,
                'result'         => 'imported',
            ];
        }

        // Recompute DTR for every affected employee-date pair
        foreach ($affected as $dates) {
            foreach ($dates as $date => $employee) {
                $this->wh->computeDailyDtr($employee, $date);
            }
        }

        return compact('imported', 'skipped', 'unknown', 'details');
    }
}

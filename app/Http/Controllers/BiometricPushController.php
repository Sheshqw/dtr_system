<?php

namespace App\Http\Controllers;

use App\Events\AttendancePunched;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ADMS Push Receiver for GRANDING / ZKTeco devices.
 *
 * The device connects TO this server and pushes attendance data.
 * Configure on device: COMM → Cloud Server Setting
 *   Server Address : 192.168.1.100  (your laptop IP)
 *   Port           : 80
 *   HTTPS          : No
 */
class BiometricPushController extends Controller
{
    public function __construct(private WorkingHoursService $wh) {}

    /**
     * Step 1 — device calls this on boot to register + get options.
     * GET /iclock/cdata?SN=XXXXX&options=all
     */
    public function init(Request $request)
    {
        $sn = $request->query('SN', 'unknown');
        Log::info("[ADMS] Device connected: SN={$sn}");

        // Tell the device our server time and basic options
        $serverTime = now()->format('Y-m-d H:i:s');

        return response(
            "GET OPTION FROM: {$sn}\n" .
            "Stamp=9999999999\n" .
            "SERVERVERSION=2.4.1\n" .
            "UPDATENUM=0\n" .
            "REALTIME=1\n" .
            "ATT_LOG_ENCRYPT=None\n",
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * Step 2 — device pushes attendance records here.
     * POST /iclock/cdata?SN=XXXXX&table=ATTLOG
     *
     * Body format (tab-separated lines):
     *   PIN\tDateTime\tStatus\tVerify\tWorkCode
     *   1\t2025-06-01 08:05:00\t0\t1\t0
     */
    public function push(Request $request)
    {
        $sn    = $request->query('SN', 'unknown');
        $table = $request->query('table', '');

        Log::info("[ADMS] Push from SN={$sn} table={$table}");

        // Only process attendance log pushes
        if (strtoupper($table) !== 'ATTLOG') {
            return response("OK: 0", 200, ['Content-Type' => 'text/plain']);
        }

        $body = $request->getContent();
        Log::info("[ADMS] Raw ATTLOG body: " . json_encode($body));
        if (empty($body)) {
            return response("OK: 0", 200, ['Content-Type' => 'text/plain']);
        }

        [$imported, $skipped, $unknown] = $this->processAttlog($body);

        Log::info("[ADMS] Processed: imported={$imported} skipped={$skipped} unknown={$unknown}");

        return response("OK: {$imported}", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Step 3 — device polls for pending commands (heartbeat).
     * GET /iclock/getrequest?SN=XXXXX
     */
    public function heartbeat(Request $request)
    {
        return response("OK", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Step 4 — device confirms it executed a command.
     * POST /iclock/devicecmd?SN=XXXXX
     */
    public function ack(Request $request)
    {
        return response("OK", 200, ['Content-Type' => 'text/plain']);
    }

    // ── Private ─────────────────────────────────────────────────────────────

    private function processAttlog(string $body): array
    {
        $imported = 0;
        $skipped  = 0;
        $unknown  = 0;
        $affected = [];

        $lines = preg_split('/\r?\n/', trim($body));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Tab-separated: PIN  DateTime  Status  Verify  WorkCode
            $parts = preg_split('/\t/', $line);
            if (count($parts) < 2) {
                // Try space-separated
                $parts = preg_split('/\s+/', $line, 5);
            }

            $pin       = trim($parts[0] ?? '');
            $datetime  = trim($parts[1] ?? '');
            $statusRaw = trim($parts[2] ?? '');

            if ($pin === '' || $datetime === '') { $skipped++; continue; }

            $employee = User::where('fingerprint_id', $pin)
                ->where('is_active', true)
                ->first();

            if (! $employee) { $unknown++; continue; }

            try {
                $punchTime = Carbon::parse($datetime);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            $workDate  = $this->wh->resolveWorkDate($employee, $punchTime);
            $punchType = match ((string) $statusRaw) {
                '0'     => 'IN',
                '1'     => 'OUT',
                default => null,
            };

            if (! $punchType) {
                $count     = AttendanceLog::where('employee_id', $employee->id)
                    ->whereDate('punch_time', $punchTime->toDateString())->count();
                $punchType = ($count % 2 === 0) ? 'IN' : 'OUT';
            }

            $created = $this->wh->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => $punchType,
                'source'     => 'biometric',
            ]);

            if (! $created) { $skipped++; continue; }

            // Must never take down attendance processing — see the identical
            // guard in BiometricController::processLogs() for why.
            if ($punchTime->isToday()) {
                try {
                    broadcast(new AttendancePunched(
                        name:        $employee->full_name,
                        employee_id: $employee->employee_id,
                        punch_type:  $punchType,
                        time:        $punchTime->format('h:i A'),
                        campus_code: $employee->campus?->code,
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Live-update broadcast failed (attendance was still recorded): ' . $e->getMessage());
                }
            }

            $affected[$employee->id][$workDate] = $employee;
            $imported++;
        }

        foreach ($affected as $dates) {
            foreach ($dates as $date => $employee) {
                $this->wh->computeDailyDtr($employee, $date);
            }
        }

        return [$imported, $skipped, $unknown];
    }
}

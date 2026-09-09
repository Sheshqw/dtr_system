<?php

namespace App\Http\Controllers;

use App\Models\SystemHealthLog;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function __construct(private SystemHealthService $health) {}

    public function index(Request $request)
    {
        $this->gate($request);

        $query = SystemHealthLog::with(['acknowledgedBy:id,first_name,last_name', 'resolvedBy:id,first_name,last_name'])
            ->latest('last_seen_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('message', 'like', "%{$s}%")
                ->orWhere('file', 'like', "%{$s}%")
            );
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $perPage   = max(1, min(100, (int) $request->get('per_page', 20)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn(SystemHealthLog $log) => $this->format($log))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem() ?? 0,
                'to'           => $paginated->lastItem() ?? 0,
            ],
            'summary' => $this->summary(),
            'types'   => SystemHealthLog::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function scan(Request $request)
    {
        $this->gate($request);

        $found = $this->health->runScan();

        return response()->json([
            'message' => $found > 0
                ? "Scan complete — {$found} issue" . ($found === 1 ? '' : 's') . ' found or updated.'
                : 'Scan complete — no issues found.',
            'found'   => $found,
            'summary' => $this->summary(),
        ]);
    }

    public function acknowledge(Request $request, SystemHealthLog $log)
    {
        $this->gate($request);

        $log->update([
            'status'          => 'acknowledged',
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json($this->format($log->fresh(['acknowledgedBy', 'resolvedBy'])));
    }

    public function resolve(Request $request, SystemHealthLog $log)
    {
        $this->gate($request);

        $log->update([
            'status'      => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json($this->format($log->fresh(['acknowledgedBy', 'resolvedBy'])));
    }

    public function reopen(Request $request, SystemHealthLog $log)
    {
        $this->gate($request);

        $log->update([
            'status'          => 'new',
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'resolved_by'     => null,
            'resolved_at'     => null,
        ]);

        return response()->json($this->format($log->fresh()));
    }

    public function destroy(Request $request, SystemHealthLog $log)
    {
        $this->gate($request);

        $log->delete();

        return response()->json(['message' => 'Log entry deleted.']);
    }

    public function clearResolved(Request $request)
    {
        $this->gate($request);

        $count = SystemHealthLog::where('status', 'resolved')->delete();

        return response()->json([
            'message' => "{$count} resolved log(s) cleared.",
            'summary' => $this->summary(),
        ]);
    }

    public function clearAll(Request $request)
    {
        $this->gate($request);

        $count = SystemHealthLog::query()->delete();

        return response()->json([
            'message' => "{$count} log(s) cleared.",
            'summary' => $this->summary(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function gate(Request $request): void
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Only Super Admin can access System Health.');
        }
    }

    private function summary(): array
    {
        return [
            'critical'     => SystemHealthLog::where('status', 'new')->where('severity', 'critical')->count(),
            'warnings'     => SystemHealthLog::where('status', 'new')->where('severity', '!=', 'critical')->count(),
            'acknowledged' => SystemHealthLog::where('status', 'acknowledged')->count(),
            'resolved'     => SystemHealthLog::where('status', 'resolved')->count(),
        ];
    }

    private function format(SystemHealthLog $log): array
    {
        return [
            'id'               => $log->id,
            'severity'         => $log->severity,
            'type'             => $log->type,
            'message'          => $log->message,
            'file'             => $log->file,
            'line'             => $log->line,
            'trace'            => $log->trace,
            'occurrences'      => $log->occurrences,
            'status'           => $log->status,
            'acknowledged_by'  => $log->acknowledgedBy?->full_name,
            'acknowledged_at'  => $log->acknowledged_at?->format('M j, Y h:i A'),
            'resolved_by'      => $log->resolvedBy?->full_name,
            'resolved_at'      => $log->resolved_at?->format('M j, Y h:i A'),
            'last_seen_at'     => $log->last_seen_at?->format('M j, Y h:i A'),
            'created_at'       => $log->created_at->format('M j, Y h:i A'),
        ];
    }
}

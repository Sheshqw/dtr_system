<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $user = $request->user();
        if (! in_array($user->role, ['super_admin', 'admin', 'hr_officer'])) {
            abort(403, 'Unauthorized.');
        }

        $query = AuditLog::with('user:id,first_name,last_name,middle_name,employee_id')
            ->latest();

        if (! $this->isSuperAdmin($request)) {
            $query->whereHas('user', fn($q) => $q->where('campus_id', $user->campus_id));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('table')) {
            $query->where('table_affected', $request->input('table'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $paginated = $query->paginate(50);

        return response()->json([
            'data' => $paginated->map(fn($log) => [
                'id'          => $log->id,
                'user'        => $log->user?->name ?? 'System',
                'employee_id' => $log->user?->employee_id ?? '—',
                'action'      => $log->action,
                'table'       => $log->table_affected,
                'record_id'   => $log->record_id,
                'reason'      => $log->reason,
                'ip'          => $log->ip_address,
                'created_at'  => $log->created_at->format('Y-m-d H:i:s'),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem() ?? 0,
                'to'           => $paginated->lastItem() ?? 0,
            ],
        ]);
    }
}

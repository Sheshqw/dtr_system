<?php

namespace App\Http\Traits;

use App\Services\FeaturePermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ScopesByCampus
{
    /**
     * Apply campus_id scope unless the authenticated user is a super_admin.
     */
    protected function campusScope(Request $request, Builder $query, string $column = 'campus_id'): Builder
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            // Super admin may optionally filter by campus via ?campus_id=
            if ($request->filled('campus_id')) {
                $query->where($column, $request->campus_id);
            }
            return $query;
        }

        return $query->where($column, $user->campus_id);
    }

    protected function resolvedCampusId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            return $request->filled('campus_id') ? (int) $request->campus_id : null;
        }

        return $user->campus_id;
    }

    protected function isSuperAdmin(Request $request): bool
    {
        return $request->user()->role === 'super_admin';
    }

    protected function isPrivileged(Request $request): bool
    {
        return in_array($request->user()->role, ['super_admin', 'admin', 'hr_officer']);
    }

    /**
     * True when the acting user may only see their own attendance records —
     * i.e. they don't hold "dtr_records.view_all" (off by default for
     * Faculty / Staff, overridable per employee in Access Control).
     */
    protected function seesOwnRecordsOnly(Request $request): bool
    {
        return ! app(FeaturePermissionService::class)->can($request->user(), 'dtr_records.view_all');
    }

    /**
     * Abort unless the acting user is a super_admin or belongs to $campusId.
     * Use this on single-record routes (update/destroy/review/...) where the
     * record was fetched by ID and campusScope()/whereHas() was never applied.
     */
    protected function assertSameCampus(Request $request, ?int $campusId, string $message = 'You do not have access to this campus\'s records.'): void
    {
        if ($this->isSuperAdmin($request)) {
            return;
        }

        if ($campusId === null || (int) $campusId !== (int) $request->user()->campus_id) {
            abort(403, $message);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\DtrLock;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DtrLockController extends Controller
{
    use ScopesByCampus;

    /**
     * List locks for the resolved campus (super_admin may pass ?campus_id=
     * to check another one). The frontend just needs "is month X locked?" —
     * returning the recent list lets it answer that for several months at
     * once without a request per cell.
     */
    public function index(Request $request)
    {
        $campusId = $this->resolvedCampusId($request);

        $query = DtrLock::with('lockedBy:id,first_name,last_name')
            ->orderByDesc('year')->orderByDesc('month');

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return response()->json($query->get()->map(fn($l) => [
            'id'        => $l->id,
            'campus_id' => $l->campus_id,
            'year'      => $l->year,
            'month'     => $l->month,
            'locked_at' => $l->locked_at,
            'locked_by' => $l->lockedBy?->full_name,
        ]));
    }

    /**
     * Lock a campus's month — admin, HR officer, or super_admin. Once
     * locked, storeLog/destroyLog/DTR-correction approval for that month
     * are refused until an Admin/Super Admin unlocks it (see unlock()).
     */
    public function lock(Request $request)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only Admin, HR Officer, or Super Admin can lock DTR records.');
        }

        $data = $request->validate([
            'campus_id' => 'required|integer|exists:campuses,id',
            'year'      => 'required|integer|min:2020|max:2099',
            'month'     => 'required|integer|min:1|max:12',
        ]);

        $this->assertSameCampus($request, (int) $data['campus_id']);

        if (DtrLock::isLocked((int) $data['campus_id'], Carbon::create($data['year'], $data['month'], 1))) {
            return response()->json(['message' => 'This month is already locked.'], 409);
        }

        $lock = DtrLock::create([
            'campus_id' => $data['campus_id'],
            'year'      => $data['year'],
            'month'     => $data['month'],
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
        ]);

        $label  = Carbon::create($data['year'], $data['month'], 1)->format('F Y');
        $campus = Campus::find($data['campus_id']);

        AuditLog::record(
            'DTR_MONTH_LOCKED',
            'dtr_locks',
            $lock->id,
            null,
            ['campus' => $campus?->name, 'year' => $data['year'], 'month' => $data['month']],
            "Locked {$label} for {$campus?->name}",
        );

        return response()->json([
            'id'        => $lock->id,
            'campus_id' => $lock->campus_id,
            'year'      => $lock->year,
            'month'     => $lock->month,
            'locked_at' => $lock->locked_at,
            'locked_by' => $request->user()->full_name,
        ], 201);
    }

    /**
     * Unlock — deliberately narrower than lock(): an HR Officer can close
     * the books for a month, but reopening one (undoing that safeguard) is
     * an Admin/Super Admin-only override.
     */
    public function unlock(Request $request, DtrLock $lock)
    {
        if (! in_array($request->user()->role, ['admin', 'super_admin'])) {
            abort(403, 'Only an Admin or Super Admin can unlock a finalized month.');
        }

        $this->assertSameCampus($request, $lock->campus_id);

        $label  = Carbon::create($lock->year, $lock->month, 1)->format('F Y');
        $campus = $lock->campus;
        $lockId = $lock->id;
        $lock->delete();

        AuditLog::record(
            'DTR_MONTH_UNLOCKED',
            'dtr_locks',
            $lockId,
            ['campus' => $campus?->name, 'year' => $lock->year, 'month' => $lock->month],
            null,
            "Unlocked {$label} for {$campus?->name}",
        );

        return response()->json(['message' => "{$label} unlocked."]);
    }
}

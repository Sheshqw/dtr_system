<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DailyDtr;
use App\Models\User;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    use ScopesByCampus;

    /**
     * Tardiness summary per employee for a given month/year.
     */
    public function tardiness(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $user  = $request->user();

        $query = DailyDtr::with(['employee.department'])
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->where('tardiness_minutes', '>', 0);

        if ($user->role === 'employee') {
            $query->where('employee_id', $user->id);
        } elseif ($user->role === 'department_head') {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $user->department_id));
        } elseif (! $this->isSuperAdmin($request)) {
            $query->whereHas('employee', fn($q) => $q->where('campus_id', $user->campus_id));
        } elseif ($request->filled('campus_id')) {
            $query->whereHas('employee', fn($q) => $q->where('campus_id', $request->campus_id));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('employee', fn($q) =>
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name',  'like', "%{$s}%")
                  ->orWhere('employee_id','like', "%{$s}%")
            );
        }

        $records = $query->orderBy('work_date')->get()
            ->groupBy('employee_id')
            ->map(function ($recs) {
                $emp    = $recs->first()->employee;
                $sorted = $recs->sortByDesc('work_date');
                return [
                    'employee_id'    => $emp->employee_id,
                    'name'           => $emp->full_name,
                    'department'     => $emp->department?->name ?? '—',
                    'times_late'     => $recs->count(),
                    'total_minutes'  => $recs->sum('tardiness_minutes'),
                    'avg_minutes'    => (int) round($recs->avg('tardiness_minutes')),
                    'max_minutes'    => $recs->max('tardiness_minutes'),
                    'last_late_date' => $sorted->first()->work_date->toDateString(),
                    'late_dates'     => $sorted->take(5)
                        ->pluck('work_date')
                        ->map(fn($d) => $d->toDateString())
                        ->values(),
                ];
            })
            ->sortByDesc('total_minutes')
            ->values();

        return response()->json($records);
    }
}

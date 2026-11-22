<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DailyDtr;
use App\Models\User;
use Carbon\Carbon;
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

        // A plain range on work_date, not whereYear()/whereMonth(): wrapping
        // the column in YEAR()/MONTH() stops the database from using its
        // work_date indexes, so every request scanned the whole table.
        // "< first of next month" (not "<= last day") also holds on SQLite,
        // where the stored value carries a 00:00:00 time.
        $monthStart = Carbon::create($year, $month, 1);

        $query = DailyDtr::with(['employee.department'])
            ->where('work_date', '>=', $monthStart->toDateString())
            ->where('work_date', '<', $monthStart->copy()->addMonth()->toDateString())
            ->where('tardiness_minutes', '>', 0);

        if ($user->role === 'employee') {
            $query->where('employee_id', $user->id);
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

    /**
     * Bradford Factor per employee: (absence instances)^2 x total absence
     * days, over a rolling window. Weighted this way because scattered
     * short absences disrupt a workplace more than one long continuous
     * one, even when the total days lost are equal — a plain absence
     * count treats both the same, this doesn't.
     */
    public function bradfordFactor(Request $request)
    {
        $days = max(7, min(365, (int) $request->get('days', 90)));
        $from = now()->subDays($days - 1)->toDateString();
        $user = $request->user();

        // "< tomorrow" rather than BETWEEN from AND today: on SQLite the
        // stored '…-22 00:00:00' sorts after a plain '…-22' bound, which
        // left today's absences out.
        $query = DailyDtr::with(['employee.department'])
            ->where('work_date', '>=', $from)
            ->where('work_date', '<', now()->addDay()->toDateString())
            ->where('status', 'absent');

        if ($user->role === 'employee') {
            $query->where('employee_id', $user->id);
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
                $sorted = $recs->sortBy('work_date')->values();

                // Group into absence "spells" — consecutive absent days,
                // bridging a single weekend, count as one instance. A
                // 5-day continuous illness should score far lower than
                // 5 separate one-day absences; a >3 calendar-day gap
                // starts a new spell.
                $instances = 0;
                $prevDate  = null;
                foreach ($sorted as $rec) {
                    if (! $prevDate || $prevDate->diffInDays($rec->work_date) > 3) {
                        $instances++;
                    }
                    $prevDate = $rec->work_date;
                }

                $totalDays = $recs->count();

                return [
                    'employee_id'    => $emp->employee_id,
                    'name'           => $emp->full_name,
                    'department'     => $emp->department?->name ?? '—',
                    'instances'      => $instances,
                    'total_days'     => $totalDays,
                    'bradford_score' => ($instances ** 2) * $totalDays,
                    'last_absence'   => $sorted->last()->work_date->toDateString(),
                    'absence_dates'  => $sorted->sortByDesc('work_date')->take(5)
                        ->pluck('work_date')
                        ->map(fn($d) => $d->toDateString())
                        ->values(),
                ];
            })
            ->filter(fn($r) => $r['instances'] > 0)
            ->sortByDesc('bradford_score')
            ->values();

        return response()->json($records);
    }
}

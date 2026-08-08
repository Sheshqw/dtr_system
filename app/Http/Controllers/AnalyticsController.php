<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DailyDtr;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ScopesByCampus;

    public function trend(Request $request)
    {
        $campusId  = $this->resolvedCampusId($request);
        $numDays   = max(7, min(90, (int) $request->get('days', 30)));
        $from      = now()->subDays($numDays - 1)->toDateString();
        $to        = now()->toDateString();
        $cacheKey  = "analytics_trend_{$numDays}_" . ($campusId ?? 'all') . "_{$to}";

        // Cache raw counts for 1 hour — keyed by days+campus+date so different
        // filter combinations get their own cache slot
        $dailyStats = Cache::remember($cacheKey, 3600, function () use ($campusId, $from, $to) {
            $query = DailyDtr::selectRaw('DATE(work_date) as date, status, COUNT(*) as count')
                ->whereBetween('work_date', [$from, $to])
                ->groupBy(DB::raw('DATE(work_date)'), 'status');

            if ($campusId) {
                $query->whereHas('employee', fn($q) => $q->where('campus_id', $campusId));
            }

            // Convert to plain arrays before caching — no Eloquent objects in cache
            return $query->get()
                ->groupBy('date')
                ->map(fn($rows) => $rows->pluck('count', 'status')->toArray())
                ->toArray();
        });

        $days = collect();
        for ($i = $numDays - 1; $i >= 0; $i--) {
            $date  = now()->subDays($i)->format('Y-m-d');
            $label = now()->subDays($i)->format('M d');
            $stats = $dailyStats[$date] ?? [];
            $days->push([
                'date'    => $date,
                'label'   => $label,
                'present' => ($stats['present'] ?? 0) + ($stats['late'] ?? 0),
                'absent'  => $stats['absent'] ?? 0,
            ]);
        }

        return response()->json($days);
    }

    public function departments(Request $request)
    {
        $campusId = $this->resolvedCampusId($request);
        $date     = $request->input('date', now()->format('Y-m-d'));
        $cacheKey = "analytics_departments_{$date}_" . ($campusId ?? 'all');

        $result = Cache::remember($cacheKey, 300, function () use ($campusId, $date) {
            $depts = Department::when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->withCount(['employees' => fn($q) => $q->where('is_active', true)])
                ->orderBy('name')
                ->get()
                ->filter(fn($d) => $d->employees_count > 0);

            if ($depts->isEmpty()) return [];

            $statsRaw = DailyDtr::selectRaw('employees.department_id, daily_dtr.status, COUNT(*) as count')
                ->join('employees', 'employees.id', '=', 'daily_dtr.employee_id')
                ->whereDate('daily_dtr.work_date', $date)
                ->whereIn('employees.department_id', $depts->pluck('id'))
                ->groupBy('employees.department_id', 'daily_dtr.status')
                ->get()
                ->groupBy('department_id');

            return $depts->map(function ($dept) use ($statsRaw) {
                $stats = ($statsRaw->get($dept->id) ?? collect())->pluck('count', 'status')->toArray();
                return [
                    'name'    => $dept->name,
                    'code'    => $dept->code,
                    'total'   => $dept->employees_count,
                    'present' => ($stats['present'] ?? 0) + ($stats['late'] ?? 0),
                    'absent'  => $stats['absent'] ?? 0,
                ];
            })->values()->toArray();
        });

        return response()->json($result
        );
    }
}

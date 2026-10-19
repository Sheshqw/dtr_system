<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\DailyDtr;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

/**
 * Personal attendance analytics for the signed-in employee's dashboard.
 * It only ever reads the requesting user's own records, so it needs no
 * role or campus scoping.
 *
 * Days are classified the same way the dashboard's month calendar does:
 * a daily_dtr row's status wins; otherwise a weekend is 'weekend' and a
 * past weekday is 'absent'; today stays open until it has a punch; and
 * days before the employee's tracking start are ignored entirely.
 */
class MyAttendanceController extends Controller
{
    private const WEEKDAY_LABELS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'];

    public function __construct(private WorkingHoursService $workingHours) {}

    public function summary(Request $request)
    {
        $request->validate(['months' => 'nullable|integer|between:1,12']);

        $user   = $request->user()->loadMissing('shift');
        $months = (int) $request->get('months', 6);
        $today  = today();
        $since  = $this->trackingStart($user);

        $windowStart = $today->copy()->startOfMonth()->subMonths($months - 1);
        $from        = $since->gt($windowStart) ? $since->copy() : $windowStart;

        $days = $from->lte($today) ? $this->classifyDays($user, $from, $today) : [];

        return response()->json([
            'since'    => $since->toDateString(),
            'shift'    => $user->shift?->only(
                'name', 'morning_in', 'morning_out', 'afternoon_in', 'afternoon_out',
                'grace_period_minutes', 'required_hours',
            ),
            'months'   => $this->monthly($days, $from, $today),
            'streak'   => $this->streak($days),
            'weekdays' => $this->weekdays($days),
        ]);
    }

    /**
     * The first day attendance should count for this employee: their hire
     * date when HR recorded one, otherwise the earlier of their account
     * creation and their first actual attendance.
     */
    private function trackingStart(User $user): Carbon
    {
        if ($user->date_hired) {
            return Carbon::parse($user->date_hired)->startOfDay();
        }

        $candidates = array_filter([
            $user->created_at?->toDateString(),
            AttendanceLog::where('employee_id', $user->id)->min('punch_time'),
            DailyDtr::where('employee_id', $user->id)
                ->whereIn('status', ['present', 'late', 'absent', 'on_leave'])
                ->min('work_date'),
        ]);

        return $candidates
            ? Carbon::parse(min(array_map(fn ($d) => substr((string) $d, 0, 10), $candidates)))->startOfDay()
            : today();
    }

    /** @return array<int, array{date: Carbon, status: string, rec: ?DailyDtr}> */
    private function classifyDays(User $user, Carbon $from, Carbon $today): array
    {
        $this->workingHours->backfillHolidays([$user->id], $from->toDateString(), $today->toDateString());

        $records = DailyDtr::where('employee_id', $user->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $today->toDateString())
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        $days = [];
        foreach (CarbonPeriod::create($from, $today) as $date) {
            $key = $date->toDateString();
            $rec = $records->get($key);

            if ($date->isSameDay($today) && (! $rec || $rec->status === 'absent')) {
                continue; // today isn't over yet
            }

            $days[] = [
                'date'   => $date->copy(),
                'status' => $rec?->status ?? ($date->isWeekend() ? 'weekend' : 'absent'),
                'rec'    => $rec,
            ];
        }

        return $days;
    }

    private function monthly(array $days, Carbon $from, Carbon $today): array
    {
        $buckets = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($today); $m->addMonth()) {
            $buckets[$m->format('Y-m')] = [
                'year' => $m->year, 'month' => $m->month, 'label' => $m->format('M Y'),
                'on_time' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'holiday' => 0,
                'hours' => 0.0, 'tardiness_minutes' => 0, 'undertime_minutes' => 0,
            ];
        }

        foreach ($days as $day) {
            $key    = $day['date']->format('Y-m');
            $status = $day['status'];
            $rec    = $day['rec'];

            match ($status) {
                'present'  => $buckets[$key]['on_time']++,
                'late'     => $buckets[$key]['late']++,
                'absent'   => $buckets[$key]['absent']++,
                'on_leave' => $buckets[$key]['on_leave']++,
                'holiday'  => $buckets[$key]['holiday']++,
                default    => null,
            };

            if (in_array($status, ['present', 'late'], true) && $rec) {
                $buckets[$key]['hours']             += (float) $rec->hours_rendered;
                $buckets[$key]['tardiness_minutes'] += (int) $rec->tardiness_minutes;
                $buckets[$key]['undertime_minutes'] += (int) $rec->undertime_minutes;
            }
        }

        return array_values(array_map(function ($b) {
            $present = $b['on_time'] + $b['late'];
            $working = $present + $b['absent']; // leave, holidays and weekends don't count against you

            return array_merge($b, [
                'present'      => $present,
                'working_days' => $working,
                'rate'         => $working ? (int) round($present / $working * 100) : null,
                'hours'        => round($b['hours'], 2),
            ]);
        }, $buckets));
    }

    /**
     * Consecutive on-time working days. Late or absent breaks the run;
     * weekends, holidays and leave are skipped without breaking it.
     */
    private function streak(array $days): array
    {
        $run = $best = 0;
        foreach ($days as $day) {
            if ($day['status'] === 'present') {
                $best = max($best, ++$run);
            } elseif (in_array($day['status'], ['late', 'absent'], true)) {
                $run = 0;
            }
        }

        return ['current' => $run, 'best' => $best];
    }

    /** Late count and average time-in per weekday, Monday to Friday. */
    private function weekdays(array $days): array
    {
        $rows = [];
        foreach (self::WEEKDAY_LABELS as $dow => $label) {
            $rows[$dow] = ['dow' => $dow, 'label' => $label, 'worked' => 0, 'late' => 0, 'arrivals' => []];
        }

        foreach ($days as $day) {
            $dow = $day['date']->dayOfWeekIso;
            if (! isset($rows[$dow]) || ! in_array($day['status'], ['present', 'late'], true)) {
                continue;
            }
            $rows[$dow]['worked']++;
            if ($day['status'] === 'late') {
                $rows[$dow]['late']++;
            }
            if ($in = $day['rec']?->morning_in) {
                [$h, $m] = array_map('intval', explode(':', $in));
                $rows[$dow]['arrivals'][] = $h * 60 + $m;
            }
        }

        return array_values(array_map(function ($r) {
            $arrivals = $r['arrivals'];
            unset($r['arrivals']);

            return $r + [
                'avg_arrival' => $arrivals ? (int) round(array_sum($arrivals) / count($arrivals)) : null,
            ];
        }, $rows));
    }
}

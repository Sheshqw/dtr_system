<?php

namespace App\Http\Controllers;

use App\Events\AttendancePunched;
use App\Http\Traits\ScopesByCampus;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use App\Models\DailyDtr;
use App\Models\Holiday;
use App\Models\User;
use App\Services\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ScopesByCampus;

    public function __construct(private WorkingHoursService $workingHours) {}

    /**
     * Receive a punch event from the biometric bridge or manual admin entry.
     */
    public function punch(Request $request)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only the biometric bridge or Super Admin/Admin/HR Officer can submit punches.');
        }

        $request->validate([
            'employee_id' => 'required|string',
            'punch_time'  => 'required|date',
            'punch_type'  => 'nullable|in:IN,OUT',
            'source'      => 'nullable|in:biometric,manual',
        ]);

        $employee = User::where('employee_id', $request->employee_id)
            ->where('is_active', true)
            ->first();

        if (! $employee) {
            return response()->json(['error' => 'Employee not found.'], 404);
        }

        $punchTime = Carbon::parse($request->punch_time);
        $workDate  = $this->workingHours->resolveWorkDate($employee, $punchTime);

        $punchType = $request->punch_type;
        if (! $punchType) {
            $todayPunches = AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('punch_time', $punchTime->toDateString())
                ->count();
            $punchType = ($todayPunches % 2 === 0) ? 'IN' : 'OUT';
        }

        $log = DB::transaction(function () use ($employee, $punchTime, $punchType, $request, $workDate) {
            $log = $this->workingHours->recordPunchIfNotDuplicate($employee->id, $punchTime, [
                'punch_type' => $punchType,
                'source'     => $request->source ?? 'biometric',
                'device_id'  => $request->device_id,
                'created_by' => null,
                'remarks'    => $request->remarks ? strip_tags(trim($request->remarks)) : null,
            ]);

            if ($log) {
                $this->workingHours->computeDailyDtr($employee, $workDate);
            }

            return $log;
        });

        if (! $log) {
            return response()->json(['message' => 'Duplicate punch ignored.']);
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

        return response()->json(['message' => 'Punch recorded.', 'log_id' => $log->id]);
    }

    /**
     * Today's attendance list for the dashboard.
     */
    public function today(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $query = User::with(['department', 'dailyDtr' => fn($q) => $q->whereDate('work_date', $date)])
            ->where('is_active', true);

        $this->campusScope($request, $query);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->whereHas('dailyDtr', fn($q) => $q->whereDate('work_date', $date)->where('status', $request->status));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('employee_id', 'like', "%{$s}%"));
        }

        $employees   = $query->orderBy('last_name')->get();
        $holidayName = Holiday::whereDate('date', $date)->value('name');

        $rows = $employees->map(function (User $emp) use ($date, $holidayName) {
            $dtr    = $emp->dailyDtr->first();
            $status = $dtr?->status ?? 'absent';
            return [
                'id'              => $emp->id,
                'employee_id'     => $emp->employee_id,
                'name'            => $emp->full_name,
                'department'      => $emp->department?->name ?? '—',
                'time_in'         => $dtr?->morning_in,
                'time_out'        => $dtr?->afternoon_out ?? $dtr?->morning_out,
                'status'          => $status,
                'holiday_name'    => $status === 'holiday' ? $holidayName : null,
                'hours_rendered'  => $dtr?->hours_rendered ?? 0,
                'tardiness_min'   => $dtr?->tardiness_minutes ?? 0,
                'overtime_min'    => $dtr?->overtime_minutes ?? 0,
            ];
        });

        $summary = [
            'present' => $rows->whereIn('status', ['present', 'late'])->count(),
            'late'    => $rows->where('status', 'late')->count(),
            'absent'  => $rows->where('status', 'absent')->count(),
        ];

        return response()->json(compact('rows', 'summary', 'date'));
    }

    /**
     * Full attendance history for one employee.
     */
    public function history(Request $request, User $employee)
    {
        $this->authorizeViewEmployee($request->user(), $employee);

        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $records = DailyDtr::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->orderBy('work_date')
            ->get();

        $totals = [
            'days_present'      => $records->whereIn('status', ['present', 'late'])->count(),
            'tardiness_minutes' => $records->sum('tardiness_minutes'),
            'overtime_minutes'  => $records->sum('overtime_minutes'),
            'undertime_minutes' => $records->sum('undertime_minutes'),
            'hours_rendered'    => round($records->sum('hours_rendered'), 2),
        ];

        return response()->json(compact('records', 'totals'));
    }

    /**
     * Raw punch logs for a given date (filterable).
     */
    public function logs(Request $request)
    {
        $date = $request->get('date', today()->toDateString());
        $user = $request->user();

        $query = AttendanceLog::with(['employee.department', 'createdBy'])
            ->whereDate('punch_time', $date)
            ->orderBy('punch_time');

        if ($user->role === 'employee') {
            $query->where('employee_id', $user->id);
        } elseif ($user->role === 'department_head') {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $user->department_id));
        } elseif (! $this->isSuperAdmin($request)) {
            $query->whereHas('employee', fn($q) => $q->where('campus_id', $user->campus_id));
        } elseif ($request->filled('campus_id')) {
            $query->whereHas('employee', fn($q) => $q->where('campus_id', $request->campus_id));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('punch_type')) {
            $query->where('punch_type', $request->punch_type);
        }

        $logs = $query->get()->map(fn($log) => [
            'id'          => $log->id,
            'employee_id' => $log->employee->employee_id,
            'name'        => $log->employee->full_name,
            'department'  => $log->employee->department?->name ?? '—',
            'punch_time'  => $log->punch_time->format('h:i:s A'),
            'punch_date'  => $log->punch_time->format('Y-m-d'),
            'punch_day'   => $log->punch_time->format('D'),
            'punch_type'  => $log->punch_type,
            'source'      => $log->source,
            'device_id'   => $log->device_id,
            'remarks'     => $log->remarks,
            'created_by'  => $log->createdBy?->full_name,
        ]);

        return response()->json($logs);
    }

    /**
     * Daily DTR records for a given month/year (filterable).
     */
    public function daily(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $user  = $request->user();

        $query = DailyDtr::with(['employee.department'])
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month);

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

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('employee', fn($q) =>
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name',  'like', "%{$s}%")
                  ->orWhere('employee_id','like', "%{$s}%")
            );
        }

        $holidayNames = Holiday::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->pluck('name', 'date')
            ->mapWithKeys(fn($name, $date) => [\Carbon\Carbon::parse($date)->toDateString() => $name]);

        $records = $query->orderBy('work_date')->orderBy('employee_id')->get()->map(fn($r) => [
            'id'                => $r->id,
            'employee_db_id'    => $r->employee_id,
            'employee_id'       => $r->employee->employee_id,
            'name'              => $r->employee->full_name,
            'department'        => $r->employee->department?->name ?? '—',
            'work_date'         => $r->work_date->toDateString(),
            'day_name'          => $r->work_date->format('D'),
            'morning_in'        => $r->morning_in   ? \Carbon\Carbon::parse($r->morning_in)->format('h:i A')   : null,
            'morning_out'       => $r->morning_out  ? \Carbon\Carbon::parse($r->morning_out)->format('h:i A')  : null,
            'afternoon_in'      => $r->afternoon_in ? \Carbon\Carbon::parse($r->afternoon_in)->format('h:i A') : null,
            'afternoon_out'     => $r->afternoon_out? \Carbon\Carbon::parse($r->afternoon_out)->format('h:i A'): null,
            'hours_rendered'    => $r->hours_rendered,
            'tardiness_minutes' => $r->tardiness_minutes,
            'overtime_minutes'  => $r->overtime_minutes,
            'undertime_minutes' => $r->undertime_minutes,
            'status'            => $r->status,
            'holiday_name'      => $r->status === 'holiday' ? ($holidayNames[$r->work_date->toDateString()] ?? null) : null,
        ]);

        return response()->json($records);
    }

    /**
     * Manual punch entry — admin / HR / super_admin only.
     */
    public function storeLog(Request $request)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'punch_time'  => 'required|date',
            'punch_type'  => 'required|in:IN,OUT',
            'remarks'     => 'nullable|string|max:500',
        ]);

        $punchTime = Carbon::parse($data['punch_time']);
        $employee  = User::find($data['employee_id']);
        $workDate  = $this->workingHours->resolveWorkDate($employee, $punchTime);

        $log = DB::transaction(function () use ($data, $punchTime, $request, $employee, $workDate) {
            $log = AttendanceLog::create([
                'employee_id' => $data['employee_id'],
                'punch_time'  => $punchTime,
                'punch_type'  => $data['punch_type'],
                'source'      => 'manual',
                'created_by'  => $request->user()->id,
                'remarks'     => isset($data['remarks']) ? strip_tags(trim($data['remarks'])) : null,
            ]);

            $this->workingHours->computeDailyDtr($employee, $workDate);

            return $log;
        });

        return response()->json(['message' => 'Punch logged.', 'log_id' => $log->id], 201);
    }

    /**
     * Delete a punch log — admin / HR / super_admin only. Recomputes DTR after deletion.
     */
    public function destroyLog(Request $request, AttendanceLog $log)
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Unauthorized.');
        }

        $employee = $log->employee;
        $workDate = $this->workingHours->resolveWorkDate($employee, $log->punch_time);

        DB::transaction(function () use ($log, $employee, $workDate) {
            $log->delete();
            $this->workingHours->computeDailyDtr($employee, $workDate);
        });

        return response()->json(['message' => 'Log deleted.']);
    }

    /**
     * Mark one or more of the acting user's own days as On Leave — no request,
     * no approval, no leave balance. Always acts on $request->user() only;
     * there is no employee_id parameter, so there is no path for marking
     * someone else's record. Allowed only on a working day (not weekend, not
     * a declared holiday) that has no punches at all.
     */
    public function markOnLeave(Request $request)
    {
        $validated = $request->validate([
            'dates'   => 'required|array|min:1|max:31',
            'dates.*' => 'date_format:Y-m-d',
        ]);

        $employee = $request->user();
        $results  = [];

        foreach ($validated['dates'] as $date) {
            $results[] = DB::transaction(fn() => $this->applyOnLeave($employee, $date));
        }

        return response()->json([
            'results' => $results,
            'marked'  => count(array_filter($results, fn($r) => $r['ok'])),
            'failed'  => count(array_filter($results, fn($r) => ! $r['ok'])),
        ]);
    }

    /**
     * Remove the On Leave mark from one or more of the acting user's own
     * days. The day is recomputed through WorkingHoursService afterward so
     * it goes back to whatever its punches (or lack of them) say it should
     * be — computeDailyDtr() preserves an existing on_leave/holiday status
     * on a no-punch day, so the status is cleared first here or that guard
     * would just put it straight back to on_leave.
     */
    public function unmarkOnLeave(Request $request)
    {
        $validated = $request->validate([
            'dates'   => 'required|array|min:1|max:31',
            'dates.*' => 'date_format:Y-m-d',
        ]);

        $employee = $request->user();
        $results  = [];

        foreach ($validated['dates'] as $date) {
            $results[] = DB::transaction(fn() => $this->removeOnLeave($employee, $date));
        }

        return response()->json([
            'results' => $results,
            'unmarked' => count(array_filter($results, fn($r) => $r['ok'])),
            'failed'   => count(array_filter($results, fn($r) => ! $r['ok'])),
        ]);
    }

    private function applyOnLeave(User $employee, string $date): array
    {
        $fail = fn(string $error) => ['date' => $date, 'ok' => false, 'error' => $error];

        $parsed = Carbon::parse($date);

        if ($parsed->isWeekend()) {
            return $fail('Weekends cannot be marked as On Leave.');
        }

        if (Holiday::whereDate('date', $date)->exists()) {
            return $fail('This day is a declared holiday.');
        }

        if (AttendanceLog::where('employee_id', $employee->id)->whereDate('punch_time', $date)->exists()) {
            return $fail('This day already has time entries.');
        }

        $dtr = DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $date)->first();

        if ($dtr && $dtr->status === 'on_leave') {
            return ['date' => $date, 'ok' => true, 'status' => 'on_leave'];
        }

        $old = $dtr?->toArray();

        $dtr ??= new DailyDtr(['employee_id' => $employee->id, 'work_date' => $date]);
        $dtr->status             = 'on_leave';
        $dtr->morning_in         = null;
        $dtr->morning_out        = null;
        $dtr->afternoon_in       = null;
        $dtr->afternoon_out      = null;
        $dtr->hours_rendered     = 0;
        $dtr->tardiness_minutes  = 0;
        $dtr->overtime_minutes   = 0;
        $dtr->undertime_minutes  = 0;
        $dtr->save();

        AuditLog::record('MARK_ON_LEAVE', 'daily_dtr', $dtr->id, $old, $dtr->fresh()->toArray());

        return ['date' => $date, 'ok' => true, 'status' => 'on_leave'];
    }

    private function removeOnLeave(User $employee, string $date): array
    {
        $fail = fn(string $error) => ['date' => $date, 'ok' => false, 'error' => $error];

        $dtr = DailyDtr::where('employee_id', $employee->id)->whereDate('work_date', $date)->first();

        if (! $dtr || $dtr->status !== 'on_leave') {
            return $fail('This day is not marked as On Leave.');
        }

        $old = $dtr->toArray();

        // Clear the status so computeDailyDtr()'s no-punch guard (which
        // intentionally preserves an existing on_leave/holiday status)
        // doesn't just hand the same value straight back.
        $dtr->status = 'absent';
        $dtr->save();

        $this->workingHours->computeDailyDtr($employee, $date);

        AuditLog::record('UNMARK_ON_LEAVE', 'daily_dtr', $dtr->id, $old, $dtr->fresh()->toArray());

        return ['date' => $date, 'ok' => true, 'status' => $dtr->fresh()->status];
    }

    private function authorizeViewEmployee(User $viewer, User $target): void
    {
        if ($this->isPrivileged(request())) {
            if ($viewer->role === 'super_admin' || $viewer->campus_id === $target->campus_id) {
                return;
            }
            abort(403, 'Unauthorized.');
        }

        if ($viewer->role === 'department_head' && $viewer->department_id === $target->department_id) {
            return;
        }

        if ($viewer->id === $target->id) {
            return;
        }

        abort(403, 'Unauthorized.');
    }
}

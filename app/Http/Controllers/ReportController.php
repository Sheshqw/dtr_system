<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\DailyDtr;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ScopesByCampus;

    public function form48(Request $request, User $employee)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2099',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        $this->authorizeView($request->user(), $employee);
        if (! $this->isPrivileged($request)) {
            abort(403, 'You can preview your DTR here. Printed or downloaded copies must be requested from HR or Admin.');
        }

        $employee->loadMissing('department', 'shift', 'campus');

        $records = DailyDtr::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn($r) => $r->work_date->day);

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $monthLabel  = Carbon::create($year, $month)->format('F Y');

        $totals = [
            'days_present'      => $records->whereIn('status', ['present', 'late'])->count(),
            'tardiness_minutes' => $records->sum('tardiness_minutes'),
            'undertime_minutes' => $records->sum('undertime_minutes'),
            'overtime_minutes'  => $records->sum('overtime_minutes'),
            'hours_rendered'    => round($records->sum('hours_rendered'), 2),
        ];

        $pdf = Pdf::loadView('reports.form48', compact(
            'employee', 'records', 'daysInMonth', 'monthLabel', 'year', 'month', 'totals'
        ))->setPaper('a4', 'portrait');

        $filename = "DTR_{$employee->employee_id}_{$year}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function preview(Request $request, User $employee)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2099',
        ]);

        $this->authorizeView($request->user(), $employee);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        $records = DailyDtr::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->orderBy('work_date')
            ->get();

        return response()->json([
            'employee'   => [
                'id'          => $employee->id,
                'name'        => $employee->full_name,
                'employee_id' => $employee->employee_id,
                'position'    => $employee->position,
                'department'  => $employee->department?->name,
                'campus'      => $employee->campus?->name ?? 'NEMSU',
            ],
            'month_label'=> Carbon::create($year, $month)->format('F Y'),
            'totals'     => [
                'days_present'      => $records->whereIn('status', ['present', 'late'])->count(),
                'leave_days'        => $records->where('status', 'on_leave')->count(),
                'tardiness_minutes' => $records->sum('tardiness_minutes'),
                'undertime_minutes' => $records->sum('undertime_minutes'),
                'overtime_minutes'  => $records->sum('overtime_minutes'),
                'hours_rendered'    => round($records->sum('hours_rendered'), 2),
            ],
            'records'    => $records->map(fn($r) => [
                'day'          => $r->work_date->day,
                'day_name'     => $r->work_date->format('D'),
                'morning_in'   => $r->morning_in,
                'morning_out'  => $r->morning_out,
                'afternoon_in' => $r->afternoon_in,
                'afternoon_out'=> $r->afternoon_out,
                'hours'        => $r->hours_rendered,
                'status'       => $r->status,
            ]),
        ]);
    }

    public function exportCsv(Request $request, User $employee): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|between:2020,2099',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        $this->authorizeView($request->user(), $employee);
        if (! $this->isPrivileged($request)) {
            abort(403, 'You can preview your DTR here. Printed or downloaded copies must be requested from HR or Admin.');
        }
        $employee->loadMissing('department', 'shift', 'campus');

        $records = DailyDtr::where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->orderBy('work_date')
            ->get();

        $monthLabel = Carbon::create($year, $month)->format('F Y');
        $filename   = "DTR_{$employee->employee_id}_{$year}_{$month}.csv";

        return response()->streamDownload(function () use ($employee, $records, $monthLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NEMSU — Daily Time Record']);
            fputcsv($out, ['Employee:', $this->csvSafe($employee->full_name)]);
            fputcsv($out, ['Employee ID:', $this->csvSafe($employee->employee_id)]);
            fputcsv($out, ['Position:', $this->csvSafe($employee->position ?? '')]);
            fputcsv($out, ['Department:', $this->csvSafe($employee->department?->name ?? '')]);
            fputcsv($out, ['Period:', $monthLabel]);
            fputcsv($out, []);
            fputcsv($out, ['Day', 'Date', 'AM In', 'AM Out', 'PM In', 'PM Out', 'Hours Rendered', 'Status', 'Tardiness (min)', 'Undertime (min)', 'Overtime (min)']);
            foreach ($records as $r) {
                fputcsv($out, [
                    $r->work_date->format('D'),
                    $r->work_date->format('Y-m-d'),
                    $r->morning_in   ?? '',
                    $r->morning_out  ?? '',
                    $r->afternoon_in ?? '',
                    $r->afternoon_out ?? '',
                    $r->hours_rendered ? round($r->hours_rendered, 2) : '',
                    $r->status,
                    $r->tardiness_minutes ?? 0,
                    $r->undertime_minutes ?? 0,
                    $r->overtime_minutes  ?? 0,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['TOTALS']);
            fputcsv($out, ['Days Present',        $records->whereIn('status', ['present', 'late'])->count()]);
            fputcsv($out, ['Total Hours Rendered', round($records->sum('hours_rendered'), 2)]);
            fputcsv($out, ['Total Tardiness (min)',$records->sum('tardiness_minutes')]);
            fputcsv($out, ['Total Undertime (min)',$records->sum('undertime_minutes')]);
            fputcsv($out, ['Total Overtime (min)', $records->sum('overtime_minutes')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportBulk(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'month'         => 'required|integer|between:1,12',
            'year'          => 'required|integer|between:2020,2099',
            'department_id' => 'nullable|integer',
            'staff_type'    => 'nullable|string',
        ]);

        if (! $this->isPrivileged($request)) {
            abort(403);
        }

        $month      = (int) $request->month;
        $year       = (int) $request->year;
        $campusId   = $this->resolvedCampusId($request);
        $deptId     = $request->department_id;
        $staffType  = $request->staff_type;
        $monthLabel = Carbon::create($year, $month)->format('F Y');
        $generated  = Carbon::now()->format('F d, Y  h:i A');
        $filename   = "DTR_Bulk_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".csv";

        $campusName = $campusId
            ? (\App\Models\Campus::find($campusId)?->name ?? 'Campus')
            : 'All Campuses';

        $employees = User::with('department')
            ->when($campusId,  fn($q) => $q->where('campus_id',     $campusId))
            ->when($deptId,    fn($q) => $q->where('department_id', $deptId))
            ->when($staffType, fn($q) => $q->where('staff_type',    $staffType))
            ->whereNotIn('role', ['super_admin', 'admin'])
            ->orderBy('last_name')
            ->get();

        $allRecords = DailyDtr::whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->when($campusId, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('campus_id', $campusId)))
            ->orderBy('work_date')
            ->get()
            ->groupBy('employee_id');

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $workingDays = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = Carbon::create($year, $month, $d)->dayOfWeek;
            if ($dow !== 0 && $dow !== 6) $workingDays++;
        }

        $statusLabel = fn($s) => match ($s) {
            'present'  => 'Present',
            'late'     => 'Late / Present',
            'absent'   => 'Absent',
            'on_leave' => 'On Leave',
            'holiday'  => 'Holiday',
            default    => ucfirst($s ?? ''),
        };

        $fmt = fn($t) => $t ? substr($t, 0, 5) : '—';

        return response()->streamDownload(function () use (
            $employees, $allRecords, $monthLabel, $campusName,
            $generated, $workingDays, $statusLabel, $fmt, $daysInMonth, $year, $month
        ) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            // ── REPORT HEADER ─────────────────────────────────────────────
            fputcsv($out, ['NORTHERN EASTERN MINDANAO STATE UNIVERSITY (NEMSU)']);
            fputcsv($out, ['DAILY TIME RECORD — BULK MONTHLY REPORT']);
            fputcsv($out, ['Campus:', $campusName]);
            fputcsv($out, ['Period:', $monthLabel]);
            fputcsv($out, ['Working Days in Month:', $workingDays]);
            fputcsv($out, ['Total Employees Included:', $employees->count()]);
            fputcsv($out, ['Date Generated:', $generated]);
            fputcsv($out, []);

            // ═══════════════════════════════════════════════════════════════
            // SECTION 1 — DAILY ATTENDANCE RECORDS
            // ═══════════════════════════════════════════════════════════════
            fputcsv($out, ['=== SECTION 1: DAILY ATTENDANCE RECORDS ===']);
            fputcsv($out, [
                'No.', 'Employee ID', 'Employee Name', 'Department', 'Position',
                'Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out',
                'Hours Rendered', 'Tardiness (min)', 'Overtime (min)', 'Undertime (min)', 'Status',
            ]);

            $rowNum = 1;
            foreach ($employees as $emp) {
                $recs = ($allRecords[$emp->id] ?? collect())->keyBy(fn($r) => $r->work_date->format('Y-m-d'));
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $date = Carbon::create($year, $month, $d);
                    $key  = $date->format('Y-m-d');
                    $dow  = $date->dayOfWeek;
                    if ($dow === 0 || $dow === 6) continue; // skip weekends

                    $rec = $recs[$key] ?? null;
                    fputcsv($out, [
                        $rowNum++,
                        $this->csvSafe($emp->employee_id ?? ''),
                        $this->csvSafe($emp->full_name),
                        $this->csvSafe($emp->department?->name ?? ''),
                        $this->csvSafe($emp->position ?? ''),
                        $date->format('m/d/Y'),
                        $date->format('D'),
                        $rec ? $fmt($rec->morning_in)   : '—',
                        $rec ? $fmt($rec->morning_out)  : '—',
                        $rec ? $fmt($rec->afternoon_in) : '—',
                        $rec ? $fmt($rec->afternoon_out): '—',
                        $rec ? number_format((float)$rec->hours_rendered, 2) : '0.00',
                        $rec ? (int)$rec->tardiness_minutes  : 0,
                        $rec ? (int)$rec->overtime_minutes   : 0,
                        $rec ? (int)$rec->undertime_minutes  : 0,
                        $rec ? $statusLabel($rec->status) : 'No Record',
                    ]);
                }
            }

            fputcsv($out, []);

            // ═══════════════════════════════════════════════════════════════
            // SECTION 2 — EMPLOYEE MONTHLY SUMMARY
            // ═══════════════════════════════════════════════════════════════
            fputcsv($out, ['=== SECTION 2: EMPLOYEE MONTHLY SUMMARY ===']);
            fputcsv($out, [
                'No.', 'Employee ID', 'Employee Name', 'Department', 'Position',
                'Working Days', 'Days Present', 'Days Late', 'Absent Days', 'Leave Days', 'Holidays',
                'Total Hours', 'Total Tardiness (min)', 'Total Overtime (min)', 'Total Undertime (min)',
                'Attendance Rate (%)',
            ]);

            $grandPresent   = 0;
            $grandAbsent    = 0;
            $grandHours     = 0;
            $grandTardiness = 0;
            $grandOT        = 0;
            $grandUT        = 0;

            $n = 1;
            foreach ($employees as $emp) {
                $recs    = $allRecords[$emp->id] ?? collect();
                $present = $recs->whereIn('status', ['present', 'late'])->count();
                $late    = $recs->where('status', 'late')->count();
                $absent  = $recs->where('status', 'absent')->count();
                $leave   = $recs->where('status', 'on_leave')->count();
                $holiday = $recs->where('status', 'holiday')->count();
                $hours   = round((float)$recs->sum('hours_rendered'), 2);
                $tard    = (int)$recs->sum('tardiness_minutes');
                $ot      = (int)$recs->sum('overtime_minutes');
                $ut      = (int)$recs->sum('undertime_minutes');
                $rate    = $workingDays > 0 ? round($present / $workingDays * 100, 1) : 0;

                $grandPresent   += $present;
                $grandAbsent    += $absent;
                $grandHours     += $hours;
                $grandTardiness += $tard;
                $grandOT        += $ot;
                $grandUT        += $ut;

                fputcsv($out, [
                    $n++,
                    $this->csvSafe($emp->employee_id ?? ''),
                    $this->csvSafe($emp->full_name),
                    $this->csvSafe($emp->department?->name ?? ''),
                    $this->csvSafe($emp->position ?? ''),
                    $workingDays,
                    $present,
                    $late,
                    $absent,
                    $leave,
                    $holiday,
                    number_format($hours, 2),
                    $tard,
                    $ot,
                    $ut,
                    $rate . '%',
                ]);
            }

            fputcsv($out, []);

            // ═══════════════════════════════════════════════════════════════
            // SECTION 3 — OVERALL TOTALS
            // ═══════════════════════════════════════════════════════════════
            fputcsv($out, ['=== SECTION 3: OVERALL CAMPUS TOTALS ===']);
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Total Employees',             $employees->count()]);
            fputcsv($out, ['Total Present Days (all)',    $grandPresent]);
            fputcsv($out, ['Total Absent Days (all)',     $grandAbsent]);
            fputcsv($out, ['Total Hours Rendered (all)',  number_format($grandHours, 2)]);
            fputcsv($out, ['Total Tardiness (min)',       $grandTardiness]);
            fputcsv($out, ['Total Overtime (min)',        $grandOT]);
            fputcsv($out, ['Total Undertime (min)',       $grandUT]);
            $avgRate = $employees->count() && $workingDays
                ? round($grandPresent / ($employees->count() * $workingDays) * 100, 1)
                : 0;
            fputcsv($out, ['Campus Attendance Rate',     $avgRate . '%']);
            fputcsv($out, []);
            fputcsv($out, ['--- End of Report ---']);
            fputcsv($out, ['Generated by NEMSU UDTRMS on ' . now()->format('F d, Y h:i A')]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export salary deduction report for tardiness and undertime (per CSC rules).
     * Daily salary rate = monthly rate / 22 working days.
     * Per-minute deduction = daily rate / 480 minutes (8-hour workday).
     */
    public function exportDeductions(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'month'         => 'required|integer|between:1,12',
            'year'          => 'required|integer|between:2020,2099',
            'department_id' => 'nullable|integer',
        ]);

        if (! $this->isPrivileged($request)) {
            abort(403);
        }

        $month      = (int) $request->month;
        $year       = (int) $request->year;
        $deptId     = $request->department_id;
        $campusId   = $this->resolvedCampusId($request);
        $monthLabel = Carbon::create($year, $month)->format('F Y');
        $filename   = "Deductions_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".csv";

        $employees = User::with('department')
            ->when($campusId,  fn($q) => $q->where('campus_id',     $campusId))
            ->when($deptId,    fn($q) => $q->where('department_id', $deptId))
            ->whereNotIn('role', ['super_admin', 'admin'])
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

        $allRecords = DailyDtr::whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->groupBy('employee_id');

        return response()->streamDownload(function () use ($employees, $allRecords, $monthLabel, $year, $month) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['NEMSU — SALARY DEDUCTION REPORT (Tardiness & Undertime)']);
            fputcsv($out, ['Period:', $monthLabel]);
            fputcsv($out, ['Note: Deduction = (minutes / 480) × daily rate. Daily rate = monthly salary / 22']);
            fputcsv($out, []);
            fputcsv($out, [
                'No.', 'Employee ID', 'Name', 'Department', 'Position',
                'Tardiness (min)', 'Undertime (min)', 'Total Deductible (min)',
                'Est. Daily Rate (₱)', 'Est. Per-Minute Rate (₱)', 'Est. Deduction (₱)',
                'Days Present', 'Days Absent', 'Leave Days',
            ]);

            $n = 1;
            foreach ($employees as $emp) {
                $recs       = $allRecords[$emp->id] ?? collect();
                $tardiness  = (int) $recs->sum('tardiness_minutes');
                $undertime  = (int) $recs->sum('undertime_minutes');
                $total      = $tardiness + $undertime;
                $present    = $recs->whereIn('status', ['present', 'late'])->count();
                $absent     = $recs->where('status', 'absent')->count();
                $leave      = $recs->where('status', 'on_leave')->count();

                fputcsv($out, [
                    $n++,
                    $this->csvSafe($emp->employee_id ?? ''),
                    $this->csvSafe($emp->full_name),
                    $this->csvSafe($emp->department?->name ?? ''),
                    $this->csvSafe($emp->position ?? ''),
                    $tardiness,
                    $undertime,
                    $total,
                    'See Note',
                    'See Note',
                    'Computed by Accounting',
                    $present,
                    $absent,
                    $leave,
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['--- End of Deduction Report ---']);
            fputcsv($out, ['Generated by NEMSU UDTRMS on ' . now()->format('F d, Y h:i A')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeView(User $viewer, User $target): void
    {
        if ($this->isPrivileged(request())) {
            if ($viewer->role === 'super_admin' || $viewer->campus_id === $target->campus_id) {
                return;
            }
            abort(403);
        }
        if ($viewer->id === $target->id) return;
        abort(403);
    }

    /**
     * Neutralizes CSV formula injection: Excel/Sheets treat a cell starting
     * with =, +, -, @, tab, or CR as a formula to evaluate. Employee name,
     * position, department, and employee_id are all free-text fields an
     * admin can set with no format restriction, so a value like
     * "=CMD(...)" would otherwise execute when the export is opened.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}

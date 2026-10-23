{{-- One employee's sheet: two identical CSC Form 48 copies side by side. --}}
@php
// Shift office hours — no leading zero, matching the official form (8:00, 12:00, 1:00, 5:00)
$shiftMorIn  = optional($employee->shift)->morning_in    ? \Carbon\Carbon::parse($employee->shift->morning_in)->format('G:i')    : '8:00';
$shiftMorOut = optional($employee->shift)->morning_out   ? \Carbon\Carbon::parse($employee->shift->morning_out)->format('G:i')   : '12:00';
$shiftAftIn  = optional($employee->shift)->afternoon_in  ? \Carbon\Carbon::parse($employee->shift->afternoon_in)->format('g:i')  : '1:00';
$shiftAftOut = optional($employee->shift)->afternoon_out ? \Carbon\Carbon::parse($employee->shift->afternoon_out)->format('g:i') : '5:00';

// Totals
$lateMins = (int)($totals['tardiness_minutes'] ?? 0);

// Employee info (uppercase to match the CSC form convention)
$empName = strtoupper($employee->full_name);

// "January - 2026" per the CSC form's month/year format. A half-month DTR
// (COS / Job Order) names its days instead: "January 1-15, 2026".
$monthName      = \Carbon\Carbon::create($year, $month)->format('F');
$monthYearLabel = $period->isHalfMonth()
    ? "{$monthName} {$period->startDay()}-{$period->endDay()}, {$year}"
    : "{$monthName} - {$year}";

// The verifying signatory — the employee's own campus director, kept on
// the campus record (Campuses page). Left blank to sign by hand if unset.
$directorName = $employee->campus?->director_name;
@endphp

<table class="pair">
<tr>
  {{-- ══ LEFT COPY ══ --}}
  <td class="copy copy-left">
    @include('reports._form48_copy', [
      'empName'        => $empName,
      'monthYearLabel' => $monthYearLabel,
      'shiftMorIn'     => $shiftMorIn,
      'shiftMorOut'    => $shiftMorOut,
      'shiftAftIn'     => $shiftAftIn,
      'shiftAftOut'    => $shiftAftOut,
      'lateMins'       => $lateMins,
      'directorName'   => $directorName,
    ])
  </td>
  {{-- ══ RIGHT COPY ══ --}}
  <td class="copy copy-right">
    @include('reports._form48_copy', [
      'empName'        => $empName,
      'monthYearLabel' => $monthYearLabel,
      'shiftMorIn'     => $shiftMorIn,
      'shiftMorOut'    => $shiftMorOut,
      'shiftAftIn'     => $shiftAftIn,
      'shiftAftOut'    => $shiftAftOut,
      'lateMins'       => $lateMins,
      'directorName'   => $directorName,
    ])
  </td>
</tr>
</table>

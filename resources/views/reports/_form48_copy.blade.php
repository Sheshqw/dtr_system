{{-- Single CSC Form 48 copy — included twice (left and right) in form48.blade.php --}}

{{-- ── Header ───────────────────────────────────── --}}
<div class="form-no">Civil Service Form No. 48</div>
<div class="form-title">Daily Time Record</div>

{{-- ── Name / Month ─────────────────────────────── --}}
<table class="info-tbl">
  <tr>
    <td>
      <span class="lbl">Name:</span>
      <span class="val val-name">{{ $empName }}</span>
    </td>
  </tr>
  <tr>
    <td><span class="lbl">For the Month</span></td>
  </tr>
  <tr>
    <td>
      <span class="lbl">of:</span>
      <span class="val val-month">{{ $monthYearLabel }}</span>
    </td>
  </tr>
</table>

{{-- ── Office hours ─────────────────────────────── --}}
<table class="oh-tbl">
  <tr>
    <td style="white-space:nowrap;">Office Hour:</td>
    <td style="white-space:nowrap; padding-left:4pt;">
      In A.M.&nbsp;<strong>{{ $shiftMorIn }}</strong>
      &nbsp;&nbsp;&nbsp;
      P.M.&nbsp;<strong>{{ $shiftAftIn }}</strong>
    </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td style="white-space:nowrap; padding-left:4pt;">
      Out A.M.&nbsp;<strong>{{ $shiftMorOut }}</strong>
      &nbsp;&nbsp;&nbsp;
      P.M.&nbsp;<strong>{{ $shiftAftOut }}</strong>
    </td>
  </tr>
</table>

{{-- ── DTR table ────────────────────────────────── --}}
<table class="dtr">
  <thead>
    <tr>
      <th rowspan="2" class="c-day">DAYS</th>
      <th colspan="2">A.M.</th>
      <th colspan="2">P.M.</th>
      <th class="c-ut">Under</th>
      <th class="c-ut">Over</th>
      <th rowspan="2" class="c-rem">Remark</th>
    </tr>
    <tr>
      <th class="c-tm">In</th>
      <th class="c-tm">Out</th>
      <th class="c-tm">In</th>
      <th class="c-tm">Out</th>
      <th class="c-ut">Time</th>
      <th class="c-ut">Time</th>
    </tr>
  </thead>
  <tbody>

    {{-- Always the full month's rows, matching the pre-printed paper form —
         for a half-month (COS / Job Order) DTR, only the requested half's
         rows carry data; the other half still appears, just blank. --}}
    @php $daysInMonth = \Carbon\Carbon::create($year, $month)->daysInMonth; @endphp
    @for ($d = 1; $d <= $daysInMonth; $d++)
      @php
        $date     = \Carbon\Carbon::create($year, $month, $d);
        $isWknd   = $date->isWeekend();
        $inPeriod = $d >= $period->startDay() && $d <= $period->endDay();
        $rec      = $inPeriod ? $records->get($d) : null;
        $status   = $inPeriod ? ($rec?->status ?? ($isWknd ? 'weekend' : 'absent')) : null;
        // That day's own holiday (name + regular/special), shown on its row
        // instead of a generic "Holiday" — on a weekend too, where the row is
        // otherwise blank. A weekday holiday the employee worked keeps its times.
        $holiday  = $inPeriod ? (($holidays ?? [])[$date->toDateString()] ?? null) : null;
        // 'absent' here only ever means "no punches", so a declared holiday
        // still reads as the holiday even if its DTR row wasn't filled in yet.
        $showHol  = $holiday && ($isWknd || in_array($status, ['holiday', 'absent'], true));

        $utMins  = ($inPeriod && ! $isWknd) ? (int)($rec?->undertime_minutes ?? 0) : 0;
        $otMins  = ($inPeriod && ! $isWknd) ? (int)($rec?->overtime_minutes  ?? 0) : 0;

        // Weekend labeling is just the calendar, not attendance data — shown
        // for every row regardless of which half was requested. HOL/LV only
        // make sense for a day actually covered by this DTR. A day with no
        // punches gets no remark: its empty time columns already say so, and
        // an "ABS" would also wrongly mark days that haven't happened yet.
        $remark  = '';
        if ($isWknd) {
            $remark = $date->isSaturday() ? 'SAT' : 'SUN';
        } elseif ($inPeriod) {
            if ($status === 'holiday' || $showHol) $remark = 'HOL';
            elseif ($status === 'on_leave') $remark = 'LV';
        }
      @endphp
      <tr class="{{ $isWknd ? 'row-wknd' : '' }}">
        <td class="c-day">{{ $d }}</td>

        @if ($showHol)
          <td colspan="4" style="font-size:5.5pt;font-style:italic;color:#555;white-space:nowrap;overflow:hidden;">{{ \Illuminate\Support\Str::limit($holiday->label(), 48) }}</td>
          <td class="c-ut"></td>
          <td class="c-ut"></td>
        @elseif ($isWknd || ! $inPeriod)
          <td class="c-tm"></td>
          <td class="c-tm"></td>
          <td class="c-tm"></td>
          <td class="c-tm"></td>
          <td class="c-ut"></td>
          <td class="c-ut"></td>
        @elseif ($status === 'holiday')
          <td colspan="4" style="font-size:5.5pt;font-style:italic;color:#555;">Holiday</td>
          <td class="c-ut"></td>
          <td class="c-ut"></td>
        @elseif ($status === 'on_leave')
          <td colspan="4" style="font-size:5.5pt;font-style:italic;color:#555;">On Leave</td>
          <td class="c-ut"></td>
          <td class="c-ut"></td>
        @else
          <td class="c-tm">{{ $rec?->morning_in   ? \Carbon\Carbon::parse($rec->morning_in)->format('h:i A')   : '' }}</td>
          <td class="c-tm">{{ $rec?->morning_out  ? \Carbon\Carbon::parse($rec->morning_out)->format('h:i A')  : '' }}</td>
          <td class="c-tm">{{ $rec?->afternoon_in  ? \Carbon\Carbon::parse($rec->afternoon_in)->format('h:i A')  : '' }}</td>
          <td class="c-tm">{{ $rec?->afternoon_out ? \Carbon\Carbon::parse($rec->afternoon_out)->format('h:i A') : '' }}</td>
          <td class="c-ut">{{ $utMins > 0 ? $utMins : '' }}</td>
          <td class="c-ut">{{ $otMins > 0 ? $otMins : '' }}</td>
        @endif

        <td class="c-rem" style="font-size:5.5pt;">{{ $remark }}</td>
      </tr>
    @endfor

  </tbody>
</table>

{{-- ── Total late in minutes ────────────────────── --}}
<div class="late-line">
  Total late in Minutes:&nbsp;&nbsp;<span class="ul-blank">{{ $lateMins > 0 ? $lateMins : '' }}</span>
</div>

{{-- ── Certification ────────────────────────────── --}}
<div class="certif">
  &nbsp;&nbsp;&nbsp;&nbsp;<span class="certif-lead">I CERTIFY</span> on my honor that the above is
  true and correct report of hours of work performed, record of which was made daily at the
  time of arrival and departure from office.
</div>

{{-- ── Signatures ───────────────────────────────────
     Two signatories only, as on the official form: the employee certifying
     the record, centred under the certification, and the Campus Director
     verifying it against the prescribed office hours. --}}
<div class="sig-block">
  <div class="sig-space"></div>
  <div class="sig-line">
    <div class="sig-name">{{ $empName }}</div>
    <div class="sig-sub">Employee's Signature</div>
  </div>
</div>

<div class="sig-label">Verified as to the prescribed office hours:</div>

<div class="sig-block">
  <div class="sig-space"></div>
  <div class="sig-line">
    @if (! empty($directorName))
      {{-- As entered on the campus record — uppercasing would turn "Ph.D." into "PH.D." --}}
      <div class="sig-name" style="text-transform: none;">{{ $directorName }}</div>
    @endif
    <div class="sig-sub" style="font-weight: bold; text-transform: uppercase;">Campus Director</div>
  </div>
</div>

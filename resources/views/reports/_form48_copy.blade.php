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

    {{-- Only the period's days: the whole month, or one half for COS / Job Order --}}
    @for ($d = $period->startDay(); $d <= $period->endDay(); $d++)
      @php
        $date    = \Carbon\Carbon::create($year, $month, $d);
        $rec     = $records->get($d);
        $isWknd  = $date->isWeekend();
        $status  = $rec?->status ?? ($isWknd ? 'weekend' : 'absent');

        $utMins  = $isWknd ? 0 : (int)($rec?->undertime_minutes ?? 0);
        $otMins  = $isWknd ? 0 : (int)($rec?->overtime_minutes  ?? 0);

        $remark  = '';
        if ($isWknd) {
            $remark = $date->isSaturday() ? 'SAT' : 'SUN';
        } else {
            if ($status === 'absent')  $remark = 'ABS';
            elseif ($status === 'holiday') $remark = 'HOL';
            elseif ($status === 'on_leave') $remark = 'LV';
        }
      @endphp
      <tr class="{{ $isWknd ? 'row-wknd' : '' }}">
        <td class="c-day">{{ $d }}</td>

        @if ($isWknd)
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

{{-- ── Signatures ───────────────────────────────── --}}
<table class="sig-tbl">
  <tr>
    {{-- Employee signature — the invisible label below matches the height of
         the reviewer column's "Verified as to..." line, so both signature
         lines sit level with each other instead of the reviewer's floating
         higher up the page. --}}
    <td style="width:46%; text-align:center;">
      <div class="sig-label" style="font-size:6.5pt; text-align:left; margin-bottom:2pt; visibility:hidden;">&nbsp;</div>
      <div class="sig-space"></div>
      <div class="sig-over"></div>
      <div class="sig-sub">Employee Signature</div>
    </td>

    <td style="width:8%;">&nbsp;</td>

    {{-- Verifying officer --}}
    <td style="width:46%; text-align:center;">
      <div class="sig-label" style="font-size:6.5pt; text-align:left; margin-bottom:2pt;">
        Verified as to the prescribed office hours:
      </div>
      @if(!empty($signingReviewer) && $signingReviewer->signature_enabled && $signingReviewer->signature_path)
        <img
          src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($signingReviewer->signature_path) }}"
          style="height:16pt; max-width:90%; margin:1pt auto 0; display:block;"
        />
      @else
        <div class="sig-space"></div>
      @endif
      <div class="sig-over"></div>
      @if(!empty($signingReviewer))
        <div class="sig-name">{{ strtoupper($signingReviewer->full_name) }}</div>
        <div class="sig-sub" style="font-weight:bold; text-transform:uppercase;">
          {{ $signingReviewer->position ?: ucwords(str_replace('_', ' ', $signingReviewer->role)) }}
        </div>
      @else
        <div class="sig-sub" style="font-weight:bold; text-transform:uppercase;">HR Officer</div>
      @endif
    </td>
  </tr>
</table>

{{-- ── Campus Director — a separate, final approving signature below
     both the employee and the reviewer, not a stand-in for either. --}}
@if(!empty($director['title']))
<div style="margin-top: 6pt; text-align: center;">
  <div class="sig-space"></div>
  <div class="sig-over" style="display: inline-block; min-width: 150pt;"></div>
  @if(!empty($director['name']))
    <div class="sig-name">{{ $director['name'] }}</div>
  @endif
  <div class="sig-sub" style="font-weight: bold; text-transform: uppercase;">{{ $director['title'] }}</div>
</div>
@endif

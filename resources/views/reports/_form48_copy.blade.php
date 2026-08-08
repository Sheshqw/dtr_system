{{-- Single CSC Form 48 copy — included twice (left and right) in form48.blade.php --}}

{{-- ── Header: form number + title + photo ────────── --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:2pt;">
  <tr>
    <td style="vertical-align:top; padding-right:3pt;">
      <div class="form-no">Civil Service Form No. 48</div>
      <div class="form-title">Daily Time Record</div>
    </td>
    <td style="vertical-align:top; text-align:center; width:42pt; padding-left:3pt;">
      @if(!empty($empPhoto))
        <img src="{{ $empPhoto }}" style="width:38pt; height:48pt; object-fit:cover; border:1pt solid #666; display:block; margin:0 auto;" />
        <div style="font-size:5pt; color:#555; margin-top:1pt;">PHOTO</div>
      @else
        <div style="width:38pt; height:48pt; border:1pt solid #999; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:5pt; color:#aaa; text-align:center; margin:0 auto;">
          <div style="font-size:8pt; color:#ccc; margin-bottom:2pt;">&#128100;</div>
          PHOTO
        </div>
      @endif
    </td>
  </tr>
</table>

{{-- ── Info fields ─────────────────────────────── --}}
<table class="info-tbl">
  <tr>
    <td style="width:58%">
      <span class="lbl">Name:</span>
      <span class="val val-lg">{{ $empName }}</span>
    </td>
    <td>
      <span class="lbl">For the Month</span>
    </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>
      <span class="lbl">of:</span>
      <span class="val val-md">{{ strtoupper($monthLabel) }}</span>
    </td>
  </tr>
</table>

{{-- ── Position / Department ──────────────────────── --}}
<table class="info-tbl" style="margin-top:-1pt;">
  <tr>
    <td style="width:58%">
      <span class="lbl">Position:</span>
      <span class="val val-lg">{{ $empPos }}</span>
    </td>
    <td>
      <span class="lbl">Department:</span>
      <span class="val val-md">{{ $empDept }}</span>
    </td>
  </tr>
</table>

{{-- ── Office hours ─────────────────────────────── --}}
<table class="oh-tbl">
  <tr>
    <td style="white-space:nowrap; font-size:7.5pt;">Office Hour:</td>
    <td style="font-size:7.5pt; white-space:nowrap; padding-left:4pt;">
      In A.M.&nbsp;<strong>{{ $shiftMorIn }}</strong>
      &nbsp;&nbsp;&nbsp;
      P.M.&nbsp;<strong>{{ $shiftAftIn }}</strong>
    </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td style="font-size:7.5pt; white-space:nowrap; padding-left:4pt;">
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
      <th rowspan="2" class="c-rem">Remarks</th>
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

    @for ($d = 1; $d <= $daysInMonth; $d++)
      @php
        $date    = \Carbon\Carbon::create($year, $month, $d);
        $rec     = $records->get($d);
        $isWknd  = $date->isWeekend();
        $status  = $rec?->status ?? ($isWknd ? 'weekend' : 'absent');

        $utMins  = $isWknd ? 0 : (int)($rec?->undertime_minutes ?? 0);
        $otMins  = $isWknd ? 0 : (int)($rec?->overtime_minutes  ?? 0);

        $remark  = '';
        if (!$isWknd) {
            if ($status === 'absent')  $remark = 'ABS';
            elseif ($status === 'holiday') $remark = 'HOL';
            elseif ($rec?->is_corrected)   $remark = '*';
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
    {{-- Employee signature --}}
    <td style="width:46%; text-align:center;">
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
      <div class="sig-space"></div>
      <div class="sig-over"></div>
      @if(!empty($director['name']))
        <div class="sig-name">{{ $director['name'] }}</div>
      @endif
      <div class="sig-sub" style="font-weight:bold; text-transform:uppercase;">{{ $director['title'] }}</div>
    </td>
  </tr>
</table>

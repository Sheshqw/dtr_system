<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CSC Form 48 – {{ $employee->full_name }} – {{ $monthLabel }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt;
  color: #000;
  background: #fff;
}
@page {
  margin: 10mm 7mm 10mm 9mm;
}

/* ── Two-up layout ────────────────────────────── */
.pair {
  width: 100%;
  border-collapse: collapse;
}
.copy {
  width: 50%;
  vertical-align: top;
  padding: 0 5pt;
}
.copy-left {
  border-right: 1pt dashed #999;
  padding-right: 6pt;
}
.copy-right {
  padding-left: 6pt;
}

/* ── Form header ──────────────────────────────── */
.form-no {
  font-size: 7pt;
  margin-bottom: 3pt;
}
.form-title {
  font-size: 13pt;
  font-weight: bold;
  text-align: center;
  letter-spacing: 2pt;
  text-transform: uppercase;
  margin-bottom: 5pt;
}

/* ── Info fields ──────────────────────────────── */
.info-tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 7.5pt;
  margin-bottom: 1pt;
}
.info-tbl td {
  padding: 1.5pt 0;
  vertical-align: bottom;
}
.lbl { white-space: nowrap; padding-right: 2pt; }
.val {
  border-bottom: 1pt solid #000;
  font-weight: normal;
  display: inline-block;
  padding: 0 2pt;
}
.val-lg  { min-width: 115pt; }
.val-md  { min-width: 80pt; }
.val-sm  { min-width: 30pt; }

/* ── Office hours ─────────────────────────────── */
.oh-tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 7.5pt;
  margin-bottom: 2pt;
}
.oh-tbl td { padding: 0 1pt; vertical-align: middle; line-height: 1.6; }

/* ── DTR table ────────────────────────────────── */
.dtr {
  width: 100%;
  border-collapse: collapse;
  margin: 2pt 0;
}
.dtr th {
  border: 1pt solid #000;
  font-size: 6.5pt;
  font-weight: bold;
  text-align: center;
  padding: 1.5pt 0;
  line-height: 1.2;
  background: #fff;
}
.dtr td {
  border: 1pt solid #000;
  font-size: 5.8pt;
  text-align: center;
  padding: 0.5pt 0;
  height: 8.5pt;
  vertical-align: middle;
  line-height: 1.1;
}
.c-day { width: 13pt; font-size: 6.5pt; font-weight: bold; color: #000; }
.c-tm  { width: 29pt; }
.c-ut  { width: 20pt; }
.c-rem { width: 18pt; }

.row-wknd td { background: #f5f5f5; color: #555; }
.row-wknd .c-day { color: #000; font-weight: bold; }

/* ── Bottom section ───────────────────────────── */
.late-line {
  font-size: 7.5pt;
  margin: 4pt 0 2pt;
  line-height: 1.8;
}
.ul-blank {
  display: inline-block;
  border-bottom: 1pt solid #000;
  min-width: 80pt;
  vertical-align: bottom;
}
.certif {
  font-size: 7.5pt;
  line-height: 1.65;
  text-align: justify;
  margin: 3pt 0 5pt;
}
.certif-lead { font-weight: bold; }

/* ── Signatures ───────────────────────────────── */
.sig-tbl {
  width: 100%;
  border-collapse: collapse;
  margin-top: 5pt;
}
.sig-tbl td {
  vertical-align: bottom;
  text-align: center;
  font-size: 7pt;
  padding: 0 2pt;
}
.sig-space { height: 18pt; }
.sig-over  { border-top: 1pt solid #000; padding-top: 1pt; }
.sig-name  { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }
.sig-sub   { font-size: 7pt; }
.sig-label { font-size: 6.5pt; text-align: left; }
</style>
</head>
<body>

@php
// Shift office hours — H:i for morning (08:00, 12:00), h:i for afternoon (01:00, 05:00)
$shiftMorIn  = optional($employee->shift)->morning_in    ? \Carbon\Carbon::parse($employee->shift->morning_in)->format('H:i')    : '08:00';
$shiftMorOut = optional($employee->shift)->morning_out   ? \Carbon\Carbon::parse($employee->shift->morning_out)->format('H:i')   : '12:00';
$shiftAftIn  = optional($employee->shift)->afternoon_in  ? \Carbon\Carbon::parse($employee->shift->afternoon_in)->format('h:i')  : '01:00';
$shiftAftOut = optional($employee->shift)->afternoon_out ? \Carbon\Carbon::parse($employee->shift->afternoon_out)->format('h:i') : '05:00';

// Totals
$lateMins  = (int)($totals['tardiness_minutes'] ?? 0);

// Employee info (uppercase to match photo)
$empName = strtoupper($employee->full_name);
$empPos  = strtoupper($employee->position ?? '');
$empDept = strtoupper($employee->department?->name ?? '');

// Employee photo (base64 for DomPDF inline embedding)
$empPhoto = '';
if (!empty($employee->photo_path)) {
    $photoPath = storage_path('app/public/' . ltrim($employee->photo_path, '/'));
    if (file_exists($photoPath)) {
        $mime     = mime_content_type($photoPath);
        $empPhoto = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($photoPath));
    }
}

// Director by campus code
$directors = [
    'TAN' => ['name' => '', 'title' => 'Campus Director'],
    'CAN' => ['name' => 'JUANCHO A. INTANO, Ph.D.', 'title' => 'Campus Director'],
    'LIA' => ['name' => '', 'title' => 'Campus Director'],
    'CAG' => ['name' => '', 'title' => 'Campus Director'],
    'SMC' => ['name' => '', 'title' => 'Campus Director'],
    'TAG' => ['name' => '', 'title' => 'Campus Director'],
    'BIS' => ['name' => '', 'title' => 'Campus Director'],
];
$campusCode = $employee->campus?->code ?? '';
$director   = $directors[$campusCode] ?? ['name' => '', 'title' => 'Campus Director'];
@endphp

<table class="pair">
<tr>
  {{-- ══ LEFT COPY ══ --}}
  <td class="copy copy-left">
    @include('reports._form48_copy', [
      'empName'     => $empName,
      'empPos'      => $empPos,
      'empDept'     => $empDept,
      'empPhoto'    => $empPhoto,
      'shiftMorIn'  => $shiftMorIn,
      'shiftMorOut' => $shiftMorOut,
      'shiftAftIn'  => $shiftAftIn,
      'shiftAftOut' => $shiftAftOut,
      'lateMins'    => $lateMins,
      'director'    => $director,
    ])
  </td>
  {{-- ══ RIGHT COPY ══ --}}
  <td class="copy copy-right">
    @include('reports._form48_copy', [
      'empName'     => $empName,
      'empPos'      => $empPos,
      'empDept'     => $empDept,
      'empPhoto'    => $empPhoto,
      'shiftMorIn'  => $shiftMorIn,
      'shiftMorOut' => $shiftMorOut,
      'shiftAftIn'  => $shiftAftIn,
      'shiftAftOut' => $shiftAftOut,
      'lateMins'    => $lateMins,
      'director'    => $director,
    ])
  </td>
</tr>
</table>

</body>
</html>

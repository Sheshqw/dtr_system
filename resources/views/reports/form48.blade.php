<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt;
  color: #000;
  background: #fff;
}
@page {
  size: A4 portrait;
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
.info-tbl td { padding: 1.5pt 0; vertical-align: bottom; }
.lbl { white-space: nowrap; padding-right: 2pt; }
.val {
  border-bottom: 1pt solid #000;
  font-weight: normal;
  display: inline-block;
  padding: 0 2pt;
}
.val-name  { min-width: 130pt; }
.val-month { min-width: 70pt; }

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
.sig-block {
  text-align: center;
  margin-top: 4pt;
}
.sig-space { height: 18pt; }
.sig-line  {
  display: inline-block;
  min-width: 150pt;
  border-top: 1pt solid #000;
  padding-top: 1pt;
}
.sig-name  { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }
.sig-sub   { font-size: 7pt; }
.sig-label { font-size: 7.5pt; text-align: left; margin-top: 8pt; }

/* ── One employee per sheet when several are printed together ── */
.sheet-break { page-break-after: always; }
</style>
</head>
<body>

@foreach ($pages as $page)
  <div class="{{ $loop->last ? '' : 'sheet-break' }}">
    @include('reports._form48_page', $page)
  </div>
@endforeach

</body>
</html>

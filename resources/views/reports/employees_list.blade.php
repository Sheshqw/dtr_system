<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Directory</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8pt;
  color: #000;
  background: #fff;
}
@page {
  margin: 10mm 8mm;
}

.header {
  text-align: center;
  margin-bottom: 10pt;
}
.header .school {
  font-size: 11pt;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: 0.5pt;
}
.header .title {
  font-size: 9.5pt;
  margin-top: 2pt;
}
.header .meta {
  font-size: 7pt;
  color: #555;
  margin-top: 3pt;
}

table.emp {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8pt;
}
table.emp th {
  background: #1B2A5E;
  color: #fff;
  font-size: 7.5pt;
  text-align: left;
  padding: 4pt 5pt;
  border: 0.5pt solid #1B2A5E;
}
table.emp td {
  font-size: 7.5pt;
  padding: 3pt 5pt;
  border: 0.5pt solid #ccc;
  vertical-align: top;
}
table.emp tr:nth-child(even) td {
  background: #F4F6FB;
}
.status-active   { color: #15803D; font-weight: bold; }
.status-inactive { color: #B91C1C; font-weight: bold; }

.footer {
  margin-top: 10pt;
  font-size: 7pt;
  color: #777;
  text-align: right;
}
</style>
</head>
<body>

<div class="header">
  <div class="school">North Eastern Mindanao State University</div>
  <div class="title">Employee Directory</div>
  <div class="meta">Generated {{ $generated }} &middot; {{ $employees->count() }} record{{ $employees->count() === 1 ? '' : 's' }}</div>
</div>

<table class="emp">
  <thead>
    <tr>
      <th>Employee ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Role</th>
      <th>Campus</th>
      <th>Department</th>
      <th>Position</th>
      <th>Date Hired</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($employees as $e)
    <tr>
      <td>{{ $e->employee_id }}</td>
      <td>{{ $e->full_name }}</td>
      <td>{{ $e->email }}</td>
      <td>{{ $e->role }}</td>
      <td>{{ $e->campus?->name }}</td>
      <td>{{ $e->department?->name }}</td>
      <td>{{ $e->position }}</td>
      <td>{{ $e->date_hired?->format('M d, Y') }}</td>
      <td class="{{ $e->is_active ? 'status-active' : 'status-inactive' }}">
        {{ $e->is_active ? 'Active' : 'Inactive' }}
      </td>
    </tr>
    @empty
    <tr><td colspan="9" style="text-align:center; padding:10pt;">No employees match the current filters.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">TimeKeep DTR System</div>

</body>
</html>

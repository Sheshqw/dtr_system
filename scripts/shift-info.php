<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shifts = DB::table('shifts')->get();
echo "SHIFTS:" . PHP_EOL;
foreach ($shifts as $s) {
    $count = DB::table('employees')->where('shift_id', $s->id)->count();
    printf("  [%d] %-30s  required=%-4sh  grace=%-3smin  assigned employees=%s\n",
        $s->id, $s->name, $s->required_hours, $s->grace_period_minutes, $count);
}

$unassigned = DB::table('employees')->whereNull('shift_id')->count();
echo PHP_EOL . "Employees with NO shift assigned: " . $unassigned . PHP_EOL;

echo PHP_EOL . "SHIFT ASSIGNMENT PER EMPLOYEE:" . PHP_EOL;
$emps = DB::table('employees')
    ->leftJoin('shifts', 'employees.shift_id', '=', 'shifts.id')
    ->select('employees.employee_id', 'employees.first_name', 'employees.last_name', 'employees.role', 'shifts.name as shift_name', 'shifts.required_hours')
    ->orderBy('employees.last_name')
    ->get();
foreach ($emps as $e) {
    printf("  %-15s  %-28s  %-12s  shift: %s\n",
        $e->employee_id,
        $e->last_name . ', ' . $e->first_name,
        $e->role,
        $e->shift_name ?? '(none)'
    );
}

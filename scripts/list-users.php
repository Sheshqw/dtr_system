<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::with('campus:id,name')
    ->orderBy('campus_id')
    ->orderBy('role')
    ->orderBy('last_name')
    ->get(['id','employee_id','first_name','last_name','email','role','campus_id']);

$currentCampus = null;
foreach ($users as $u) {
    $campusName = $u->campus?->name ?? '(Super Admin — No Campus)';
    if ($campusName !== $currentCampus) {
        $currentCampus = $campusName;
        echo PHP_EOL . str_repeat('─', 70) . PHP_EOL;
        echo "  " . strtoupper($campusName) . PHP_EOL;
        echo str_repeat('─', 70) . PHP_EOL;
    }
    $name = trim(($u->last_name ?? '') . ', ' . ($u->first_name ?? ''));
    printf("  %-18s  %-14s  %-28s  %s\n",
        $u->role,
        $u->employee_id ?? '—',
        $name,
        $u->email
    );
}
echo PHP_EOL . "Total: " . $users->count() . " accounts" . PHP_EOL;

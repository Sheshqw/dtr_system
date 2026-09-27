<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// One-off seeder to bring real test employees from local development onto
// production, with freshly-generated passwords (never reusing local hashes).
// Safe to re-run: every user is keyed by employee_id via firstOrCreate.
class RealEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'ADMIN' => 'Administration',
            'DTE'   => 'Department of Teacher Education',
            'DITCS' => 'Department of Information Technology and Computer Studies',
            'DC'    => 'Department of Criminology',
            'DHTM'  => 'Department of Hospitality and Tourism Management',
        ];
        foreach ($departments as $code => $name) {
            Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $shifts = [
            'Standard (8 hrs)' => [
                'morning_in' => '08:00:00', 'morning_out' => '12:00:00',
                'afternoon_in' => '13:00:00', 'afternoon_out' => '17:00:00',
                'grace_period_minutes' => 15, 'required_hours' => 8, 'staff_type' => null,
            ],
            'Security Day Shift' => [
                'morning_in' => '06:00', 'morning_out' => '12:00',
                'afternoon_in' => '12:00', 'afternoon_out' => '18:30',
                'grace_period_minutes' => 15, 'required_hours' => 12, 'staff_type' => 'security',
            ],
            'Faculty / Instructor Shift' => [
                'morning_in' => '07:00', 'morning_out' => '11:40:00',
                'afternoon_in' => '13:00', 'afternoon_out' => '17:00',
                'grace_period_minutes' => 15, 'required_hours' => 8, 'staff_type' => 'faculty',
            ],
        ];
        foreach ($shifts as $name => $attrs) {
            Shift::firstOrCreate(['name' => $name], $attrs);
        }

        $cantilan = Campus::where('code', 'CAN')->first();

        $employees = [
            ['id' => '10',        'first' => 'Ianle',      'last' => 'Talan',    'email' => 'sullanotalanianle@gmail.com', 'campus' => $cantilan, 'dept' => 'DITCS', 'shift' => 'Faculty / Instructor Shift',  'position' => 'Instructor I',   'status' => 'permanent',   'role' => 'employee'],
            ['id' => '2020-674',  'first' => 'Sharon',     'last' => 'Cruz',     'email' => 'Sharon@gmail.com',            'campus' => $cantilan, 'dept' => 'DTE',   'shift' => 'Faculty / Instructor Shift',  'position' => 'Professor II',  'status' => 'permanent',   'role' => 'employee'],
            ['id' => '2021-0707', 'first' => 'Jayson',     'last' => 'Laste',    'email' => 'Jayson@gmail.com',            'campus' => $cantilan, 'dept' => 'DITCS', 'shift' => 'Faculty / Instructor Shift',  'position' => 'Instructor III','status' => 'permanent',   'role' => 'employee'],
            ['id' => '2021-6767', 'first' => 'Jayson',     'last' => 'Jamora',   'email' => 'jayson.jamora@gmail.com',     'campus' => $cantilan, 'dept' => null,    'shift' => 'Security Day Shift',          'position' => 'Security Guard','status' => 'permanent',   'role' => 'employee'],
            ['id' => '2022-5343', 'first' => 'Jane',       'last' => 'Jones',    'email' => 'Jane@gmail.com',              'campus' => $cantilan, 'dept' => 'DHTM',  'shift' => 'Faculty / Instructor Shift',  'position' => 'Instructor III','status' => 'contractual', 'role' => 'employee'],
            ['id' => '2023-07',   'first' => 'Joshua',     'last' => 'Lascay',   'email' => 'lascay@gmail.com',            'campus' => $cantilan, 'dept' => 'DC',    'shift' => 'Faculty / Instructor Shift',  'position' => 'Instructor II', 'status' => 'contractual', 'role' => 'employee'],
            ['id' => '2023-1099', 'first' => 'Ian',        'last' => 'Nantes',   'email' => 'ian@gmail.com',               'campus' => $cantilan, 'dept' => null,    'shift' => 'Standard (8 hrs)',            'position' => 'Instructor 2',  'status' => 'contractual', 'role' => 'employee'],
            ['id' => '2023-18828','first' => 'Lyndon',     'last' => 'Rosas',    'email' => 'marknantes632@gmail.com',     'campus' => null,      'dept' => 'ADMIN', 'shift' => 'Standard (8 hrs)',            'position' => 'Instructor',    'status' => 'permanent',   'role' => 'employee'],
            ['id' => '9',         'first' => 'Shekainah',  'last' => 'agustin',  'email' => 'shekainahagustin@gmail.com',  'campus' => $cantilan, 'dept' => 'DITCS', 'shift' => 'Faculty / Instructor Shift',  'position' => 'Instructor I',  'status' => 'permanent',   'role' => 'employee'],
        ];

        foreach ($employees as $e) {
            $existing = User::where('employee_id', $e['id'])->first();
            if ($existing) {
                $this->command?->info("Skipped {$e['first']} {$e['last']} ({$e['id']}) — already exists.");
                continue;
            }

            $password = Str::password(12);

            User::create([
                'employee_id'       => $e['id'],
                'first_name'        => $e['first'],
                'last_name'         => $e['last'],
                'email'             => $e['email'],
                'password'          => Hash::make($password),
                'role'              => $e['role'],
                'campus_id'         => $e['campus']?->id,
                'department_id'     => $e['dept'] ? Department::where('code', $e['dept'])->first()?->id : null,
                'shift_id'          => Shift::where('name', $e['shift'])->first()?->id,
                'position'          => $e['position'],
                'employment_status' => $e['status'],
                'is_active'         => true,
            ]);

            $this->command?->info("Created {$e['first']} {$e['last']} ({$e['id']}) — {$e['email']} / {$password}");
        }
    }
}

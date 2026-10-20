<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default shift
        $shift = Shift::create([
            'name'                 => 'Standard (8 hrs)',
            'morning_in'           => '08:00:00',
            'morning_out'          => '12:00:00',
            'afternoon_in'         => '13:00:00',
            'afternoon_out'        => '17:00:00',
            'grace_period_minutes' => 15,
            'required_hours'       => 8.00,
        ]);

        // Admin department placeholder
        $adminDept = Department::create([
            'name' => 'Administration',
            'code' => 'ADMIN',
        ]);

        // Default admin account
        $admin = User::create([
            'employee_id'       => 'ADMIN-001',
            'first_name'        => 'System',
            'last_name'         => 'Administrator',
            'email'             => 'admin@nemsu-cantilan.edu.ph',
            'password'          => Hash::make('Admin@1234'),
            'role'              => 'hr_officer',
            'department_id'     => $adminDept->id,
            'shift_id'          => $shift->id,
            'position'          => null,
            'employment_status' => 'permanent',
            'is_active'         => true,
        ]);

        // Set department head
        $adminDept->update(['head_id' => $admin->id]);

        // Sample HR department
        $hrDept = Department::create([
            'name' => 'Human Resources',
            'code' => 'HR',
        ]);

        // Sample HR Officer
        User::create([
            'employee_id'       => 'HR-001',
            'first_name'        => 'Maria',
            'last_name'         => 'Santos',
            'email'             => 'hr@nemsu-cantilan.edu.ph',
            'password'          => Hash::make('HR@1234'),
            'role'              => 'hr_officer',
            'department_id'     => $hrDept->id,
            'shift_id'          => $shift->id,
            'position'          => 'HR Officer',
            'employment_status' => 'permanent',
            'is_active'         => true,
        ]);

        // Sample Faculty Department
        $facultyDept = Department::create([
            'name' => 'College of Computer Studies',
            'code' => 'CCS',
        ]);

        // Sample employee
        User::create([
            'employee_id'       => 'CCS-001',
            'first_name'        => 'Juan',
            'last_name'         => 'Dela Cruz',
            'email'             => 'jdelacruz@nemsu-cantilan.edu.ph',
            'password'          => Hash::make('Employee@1234'),
            'role'              => 'employee',
            'department_id'     => $facultyDept->id,
            'shift_id'          => $shift->id,
            'position'          => 'Instructor I',
            'employment_status' => 'permanent',
            'is_active'         => true,
        ]);
    }
}

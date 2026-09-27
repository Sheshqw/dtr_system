<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Creates one HR Officer account per campus, mirroring local development's
// per-campus HR placeholders. Fresh random passwords are generated on the
// production side rather than reusing local hashes. Safe to re-run.
class CampusHrOfficerSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'ADMIN')->first();
        $shift      = Shift::where('name', 'Standard (8 hrs)')->first();

        $officers = [
            ['id' => 'HR-CAN', 'campus' => 'CAN', 'first' => 'Cantilan',   'email' => 'hr.can@nemsu.edu.ph'],
            ['id' => 'HR-TAN', 'campus' => 'TAN', 'first' => 'Tandag',     'email' => 'hr.tan@nemsu.edu.ph'],
            ['id' => 'HR-LIA', 'campus' => 'LIA', 'first' => 'Lianga',     'email' => 'hr.lia@nemsu.edu.ph'],
            ['id' => 'HR-BIS', 'campus' => 'BIS', 'first' => 'Bislig',     'email' => 'hr.bis@nemsu.edu.ph'],
            ['id' => 'HR-SMC', 'campus' => 'SMC', 'first' => 'SanMiguel',  'email' => 'hr.smc@nemsu.edu.ph'],
            ['id' => 'HR-CAG', 'campus' => 'CAG', 'first' => 'Cagwait',    'email' => 'hr.cag@nemsu.edu.ph'],
            ['id' => 'HR-TAG', 'campus' => 'TAG', 'first' => 'Tagbina',    'email' => 'hr.tag@nemsu.edu.ph'],
        ];

        foreach ($officers as $o) {
            if (User::where('employee_id', $o['id'])->exists()) {
                $this->command?->info("Skipped {$o['id']} — already exists.");
                continue;
            }

            $password = Str::password(12);
            $campus   = Campus::where('code', $o['campus'])->first();

            User::create([
                'employee_id'       => $o['id'],
                'first_name'        => $o['first'],
                'last_name'         => 'HROfficer',
                'email'             => $o['email'],
                'password'          => Hash::make($password),
                'role'              => 'hr_officer',
                'campus_id'         => $campus?->id,
                'department_id'     => $department?->id,
                'shift_id'          => $shift?->id,
                'position'          => 'HR Officer',
                'employment_status' => 'permanent',
                'is_active'         => true,
            ]);

            $this->command?->info("Created {$o['first']} HROfficer ({$o['id']}) — {$o['email']} / {$password}");
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@nemsu.edu.ph'],
            [
                'employee_id'       => 'SA-001',
                'first_name'        => 'Super',
                'last_name'         => 'Administrator',
                'email'             => 'superadmin@nemsu.edu.ph',
                'password'          => Hash::make('SuperAdmin@1234'),
                'role'              => 'super_admin',
                'campus_id'         => null,
                'position'          => 'System Super Administrator',
                'employment_status' => 'permanent',
                'is_active'         => true,
            ]
        );
    }
}

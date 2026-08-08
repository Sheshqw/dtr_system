<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CampusAdminSeeder extends Seeder
{
    public function run(): void
    {
        $shift = Shift::first();

        $campuses = Campus::all()->keyBy('code');

        $accounts = [
            'CAN' => ['employee_id' => 'ADM-CAN', 'email' => 'admin.can@nemsu.edu.ph', 'name' => ['Cantilan', 'Admin']],
            'TAN' => ['employee_id' => 'ADM-TAN', 'email' => 'admin.tan@nemsu.edu.ph', 'name' => ['Tandag',   'Admin']],
            'LIA' => ['employee_id' => 'ADM-LIA', 'email' => 'admin.lia@nemsu.edu.ph', 'name' => ['Lianga',   'Admin']],
            'BIS' => ['employee_id' => 'ADM-BIS', 'email' => 'admin.bis@nemsu.edu.ph', 'name' => ['Bislig',   'Admin']],
            'MAR' => ['employee_id' => 'ADM-MAR', 'email' => 'admin.mar@nemsu.edu.ph', 'name' => ['Marihatag','Admin']],
            'BAR' => ['employee_id' => 'ADM-BAR', 'email' => 'admin.bar@nemsu.edu.ph', 'name' => ['Barobo',   'Admin']],
            'LIN' => ['employee_id' => 'ADM-LIN', 'email' => 'admin.lin@nemsu.edu.ph', 'name' => ['Lingig',   'Admin']],
            'SMC' => ['employee_id' => 'ADM-SMC', 'email' => 'admin.smc@nemsu.edu.ph', 'name' => ['San Miguel','Admin']],
            'LAN' => ['employee_id' => 'ADM-LAN', 'email' => 'admin.lan@nemsu.edu.ph', 'name' => ['Lanuza',   'Admin']],
            'CAG' => ['employee_id' => 'ADM-CAG', 'email' => 'admin.cag@nemsu.edu.ph', 'name' => ['Cagwait',  'Admin']],
        ];

        foreach ($accounts as $code => $info) {
            $campus = $campuses->get($code);
            if (! $campus) continue;

            // Create an Administration department for this campus if it doesn't exist
            $dept = Department::firstOrCreate(
                ['code' => 'ADMIN-' . $code],
                [
                    'name'      => 'Administration',
                    'code'      => 'ADMIN-' . $code,
                    'campus_id' => $campus->id,
                ]
            );

            User::firstOrCreate(
                ['email' => $info['email']],
                [
                    'employee_id'       => $info['employee_id'],
                    'first_name'        => $info['name'][0],
                    'last_name'         => $info['name'][1],
                    'email'             => $info['email'],
                    'password'          => Hash::make('Admin@1234'),
                    'role'              => 'admin',
                    'campus_id'         => $campus->id,
                    'department_id'     => $dept->id,
                    'shift_id'          => $shift?->id,
                    'position'          => 'Campus Administrator',
                    'employment_status' => 'permanent',
                    'is_active'         => true,
                ]
            );
        }

        // Also assign the existing Cantilan admin to the Cantilan campus
        $canCampus = $campuses->get('CAN');
        if ($canCampus) {
            User::where('email', 'admin@nemsu-cantilan.edu.ph')
                ->whereNull('campus_id')
                ->update(['campus_id' => $canCampus->id]);
        }
    }
}

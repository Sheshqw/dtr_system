<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RefreshCampusesSeeder extends Seeder
{
    // Correct 7 NEMSU campuses
    private const CAMPUSES = [
        ['name' => 'Tandag Campus',    'code' => 'TAN', 'address' => 'Tandag City, Surigao del Sur',  'is_active' => true],
        ['name' => 'Cantilan Campus',  'code' => 'CAN', 'address' => 'Cantilan, Surigao del Sur',     'is_active' => true],
        ['name' => 'Lianga Campus',    'code' => 'LIA', 'address' => 'Lianga, Surigao del Sur',       'is_active' => true],
        ['name' => 'Cagwait Campus',   'code' => 'CAG', 'address' => 'Cagwait, Surigao del Sur',      'is_active' => true],
        ['name' => 'San Miguel Campus','code' => 'SMC', 'address' => 'San Miguel, Surigao del Sur',   'is_active' => true],
        ['name' => 'Tagbina Campus',   'code' => 'TAG', 'address' => 'Tagbina, Surigao del Sur',      'is_active' => true],
        ['name' => 'Bislig Campus',    'code' => 'BIS', 'address' => 'Bislig City, Surigao del Sur',  'is_active' => true],
    ];

    // Campuses to remove (no longer part of NEMSU DTR system)
    private const REMOVE_CODES = ['MAR', 'BAR', 'LIN', 'LAN'];

    public function run(): void
    {
        // ── 1. Upsert the 7 correct campuses ───────────────────────
        foreach (self::CAMPUSES as $data) {
            Campus::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        // ── 2. Deactivate admins of removed campuses & clean up ────
        $removedCampuses = Campus::whereIn('code', self::REMOVE_CODES)->get();

        foreach ($removedCampuses as $campus) {
            // Deactivate employees assigned to this campus
            User::where('campus_id', $campus->id)->update(['is_active' => false]);

            // Delete departments belonging to this campus
            Department::where('campus_id', $campus->id)->delete();

            // Delete the campus
            $campus->delete();
        }

        // ── 3. Ensure one admin account per remaining campus ───────
        $shift = \App\Models\Shift::first();

        $admins = [
            'TAN' => ['employee_id' => 'ADM-TAN', 'email' => 'admin.tan@nemsu.edu.ph', 'name' => ['Tandag',    'Admin']],
            'CAN' => ['employee_id' => 'ADM-CAN', 'email' => 'admin.can@nemsu.edu.ph', 'name' => ['Cantilan',  'Admin']],
            'LIA' => ['employee_id' => 'ADM-LIA', 'email' => 'admin.lia@nemsu.edu.ph', 'name' => ['Lianga',    'Admin']],
            'CAG' => ['employee_id' => 'ADM-CAG', 'email' => 'admin.cag@nemsu.edu.ph', 'name' => ['Cagwait',   'Admin']],
            'SMC' => ['employee_id' => 'ADM-SMC', 'email' => 'admin.smc@nemsu.edu.ph', 'name' => ['San Miguel','Admin']],
            'TAG' => ['employee_id' => 'ADM-TAG', 'email' => 'admin.tag@nemsu.edu.ph', 'name' => ['Tagbina',   'Admin']],
            'BIS' => ['employee_id' => 'ADM-BIS', 'email' => 'admin.bis@nemsu.edu.ph', 'name' => ['Bislig',    'Admin']],
        ];

        foreach ($admins as $code => $info) {
            $campus = Campus::where('code', $code)->first();
            if (! $campus) continue;

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
                    'role'              => 'hr_officer',
                    'campus_id'         => $campus->id,
                    'department_id'     => $dept->id,
                    'shift_id'          => $shift?->id,
                    'position'          => null,
                    'employment_status' => 'permanent',
                    'is_active'         => true,
                ]
            );
        }

        // ── 4. Assign existing Cantilan seeded admin to CAN campus ─
        $canCampus = Campus::where('code', 'CAN')->first();
        if ($canCampus) {
            User::where('email', 'admin@nemsu-cantilan.edu.ph')
                ->whereNull('campus_id')
                ->update(['campus_id' => $canCampus->id]);
        }

        $this->command->info('Campuses updated successfully:');
        Campus::orderBy('name')->get()->each(
            fn($c) => $this->command->line("  [{$c->code}] {$c->name}")
        );
    }
}

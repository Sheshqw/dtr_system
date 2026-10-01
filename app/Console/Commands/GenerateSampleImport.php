<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Department;
use Illuminate\Console\Command;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Generates a realistic bulk-import workbook for load-testing the Employee
 * import (EmployeeController::import) at real scale — 2,600 employees being
 * the panel's stated minimum target. Pulls real Campus/Department names from
 * the database so the generated rows actually resolve during import instead
 * of failing on "not found"; falls back to leaving those columns blank
 * (campus/department are optional on import) if none exist yet.
 */
class GenerateSampleImport extends Command
{
    protected $signature = 'employees:generate-sample-import
                            {--count=2600 : How many employee rows to generate}
                            {--output= : Output file path (defaults to storage/app/sample_import_{count}.xlsx)}';

    protected $description = 'Generate a realistic sample employee import workbook for load-testing the bulk import';

    private const FIRST_NAMES = [
        'Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Antonio', 'Carmen', 'Francisco', 'Luz',
        'Ramon', 'Teresa', 'Ricardo', 'Elena', 'Eduardo', 'Josefina', 'Manuel', 'Corazon', 'Roberto', 'Angelica',
        'Fernando', 'Dolores', 'Carlos', 'Remedios', 'Miguel', 'Esperanza', 'Alfredo', 'Victoria', 'Rodrigo', 'Gloria',
        'Reynaldo', 'Marilou', 'Danilo', 'Jasmine', 'Arnel', 'Precious', 'Jerome', 'Angelo', 'Michael', 'Christian',
    ];

    private const LAST_NAMES = [
        'Dela Cruz', 'Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos',
        'Villanueva', 'Castillo', 'Gonzales', 'Rivera', 'Aquino', 'Fernandez', 'Pascual', 'Soriano', 'Salazar', 'Navarro',
        'Agustin', 'Tolentino', 'Ignacio', 'Lazaro', 'Mercado', 'Domingo', 'Marquez', 'Padilla', 'Alcantara', 'Bernardo',
    ];

    private const STAFF_TYPES = ['faculty', 'administrative', 'security', 'custodial', 'maintenance', 'laboratory', 'driver', 'other'];

    private const POSITIONS = [
        'faculty'        => ['Instructor I', 'Instructor II', 'Assistant Professor I', 'Associate Professor I', 'Professor I'],
        'administrative' => ['Administrative Aide I', 'Administrative Assistant II', 'Administrative Officer III', 'Accountant II', 'Human Resource Management Officer I'],
        'security'       => ['Security Guard I', 'Security Guard II', 'Security Officer I'],
        'custodial'      => ['Utility Worker I', 'Utility Worker II', 'Laborer I'],
        'maintenance'    => ['Carpenter I', 'Electrician I', 'Maintenance Worker I', 'Plumber I'],
        'laboratory'     => ['Laboratory Aide', 'Laboratory Technician I', 'Science Research Specialist I'],
        'driver'         => ['Driver I', 'Driver II'],
        'other'          => ['Guidance Counselor I', 'Nurse I', 'Extension Specialist I'],
    ];

    private const DESIGNATIONS = ['', '', '', '', 'Program Chair', 'Department Head', 'Dean', 'Coordinator'];

    private const EMPLOYMENT_STATUSES = ['permanent', 'job_order', 'cos', 'part-time', 'sa', 'ojt'];

    public function handle(): int
    {
        $count  = max(1, (int) $this->option('count'));
        $output = $this->option('output') ?: storage_path("app/sample_import_{$count}.xlsx");

        $campusNames = Campus::pluck('name')->all();
        $departments = Department::all(['name', 'campus_id'])
            ->groupBy('campus_id')
            ->map(fn($group) => $group->pluck('name')->all());
        $campusesById = Campus::pluck('name', 'id');

        if (empty($campusNames)) {
            $this->warn('No campuses found — Campus/Department columns will be left blank. Seed campuses first for a more realistic file.');
        }

        $this->info("Generating {$count} sample employee rows…");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $lastName  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $staffType = self::STAFF_TYPES[array_rand(self::STAFF_TYPES)];
            $positions = self::POSITIONS[$staffType];

            $campusId   = null;
            $campusName = '';
            if (! empty($campusNames)) {
                $campusId   = array_rand($campusesById->all());
                $campusName = $campusesById[$campusId];
            }

            $deptOptions    = $departments[$campusId] ?? [];
            $departmentName = $deptOptions ? $deptOptions[array_rand($deptOptions)] : '';

            $birthYear = random_int(1965, 2002);
            $birthDate = sprintf('%d-%02d-%02d', $birthYear, random_int(1, 12), random_int(1, 28));

            $rows[] = [
                'Employee ID'       => sprintf('EMP-%s-%05d', date('Y'), $i),
                'Last Name'         => $lastName,
                'First Name'        => $firstName,
                'Middle Name'       => '',
                'Gender'            => $i % 2 === 0 ? 'Male' : 'Female',
                'Birthdate'         => $birthDate,
                'Email'             => '',
                'Password'          => '',
                'Role'              => 'employee',
                'Campus'            => $campusName,
                'Department'        => $departmentName,
                'Position'          => $positions[array_rand($positions)],
                'Designation'       => self::DESIGNATIONS[array_rand(self::DESIGNATIONS)],
                'Monthly Salary'    => random_int(18, 95) * 1000,
                'Staff Type'        => $staffType,
                'Employment Status' => self::EMPLOYMENT_STATUSES[array_rand(self::EMPLOYMENT_STATUSES)],
                'Date Hired'        => sprintf('%d-%02d-%02d', random_int(2015, 2026), random_int(1, 12), random_int(1, 28)),
            ];

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        (new FastExcel(collect($rows)))->export($output);

        $this->info("Wrote {$count} rows to {$output}");

        return self::SUCCESS;
    }
}

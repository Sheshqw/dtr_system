<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Replaces the generic 'casual'/'contractual' options with the classifications
// actually used at a Philippine SUC: Permanent, Job Order, COS (Contract of
// Service), Part-time, SA (Student Assistant), OJT.
return new class extends Migration
{
    public function up(): void
    {
        // Remap existing data to its closest real-world equivalent before
        // narrowing the enum, so no existing employee's status is dropped.
        DB::table('employees')->where('employment_status', 'casual')->update(['employment_status' => 'job_order']);
        DB::table('employees')->where('employment_status', 'contractual')->update(['employment_status' => 'cos']);

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('employees', function ($table) {
                $table->string('employment_status', 30)->nullable()->change();
            });
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY employment_status ENUM('permanent','job_order','cos','part-time','sa','ojt') NULL");
    }

    public function down(): void
    {
        DB::table('employees')->where('employment_status', 'job_order')->update(['employment_status' => 'casual']);
        DB::table('employees')->where('employment_status', 'cos')->update(['employment_status' => 'contractual']);
        DB::table('employees')->whereIn('employment_status', ['sa', 'ojt'])->update(['employment_status' => null]);

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('employees', function ($table) {
                $table->string('employment_status', 30)->nullable()->change();
            });
            return;
        }

        DB::statement("ALTER TABLE employees MODIFY employment_status ENUM('permanent','casual','contractual','part-time') NULL");
    }
};

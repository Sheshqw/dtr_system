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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite's "enum" here is a plain string column with no CHECK
            // constraint, so it accepts any value regardless of order.
            Schema::table('employees', function ($table) {
                $table->string('employment_status', 30)->nullable()->change();
            });
            DB::table('employees')->where('employment_status', 'casual')->update(['employment_status' => 'job_order']);
            DB::table('employees')->where('employment_status', 'contractual')->update(['employment_status' => 'cos']);
            return;
        }

        // Widen to the union of old + new values first -- MySQL's ENUM
        // rejects (truncates, fatally under strict mode) any value not in
        // the *current* definition, so remapping 'contractual' -> 'cos'
        // below would fail if 'cos' isn't already an accepted value yet.
        DB::statement("ALTER TABLE employees MODIFY employment_status ENUM('permanent','casual','contractual','part-time','job_order','cos','sa','ojt') NULL");

        // Remap existing data to its closest real-world equivalent before
        // narrowing the enum, so no existing employee's status is dropped.
        DB::table('employees')->where('employment_status', 'casual')->update(['employment_status' => 'job_order']);
        DB::table('employees')->where('employment_status', 'contractual')->update(['employment_status' => 'cos']);

        // Now safe to narrow to the final set -- no row still holds a
        // value outside it.
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

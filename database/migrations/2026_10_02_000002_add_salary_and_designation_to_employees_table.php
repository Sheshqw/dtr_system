<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Designation is a distinct field from Position (e.g. "Dean", "Program Chair"
// vs. "Instructor I") — some employees hold an administrative designation on
// top of their teaching/staff position. Salary is confidential: gated to
// Super Admin/Admin/HR Officer in EmployeeController::format() and the
// Excel/PDF exports, never exposed to any other role.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('salary', 12, 2)->nullable()->after('position');
            $table->string('designation', 100)->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['salary', 'designation']);
        });
    }
};

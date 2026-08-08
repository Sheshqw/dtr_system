<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('suffix', 10)->nullable()->after('middle_name');
            $table->enum('gender', ['male', 'female'])->nullable()->after('suffix');
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated'])->nullable()->after('gender');
            $table->enum('staff_type', [
                'faculty',
                'administrative',
                'security',
                'custodial',
                'maintenance',
                'laboratory',
                'driver',
                'other',
            ])->nullable()->after('employment_status');
            $table->string('contact_number', 30)->nullable()->after('staff_type');
            $table->date('date_of_birth')->nullable()->after('contact_number');
            $table->date('date_hired')->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['suffix', 'gender', 'civil_status', 'staff_type', 'contact_number', 'date_of_birth', 'date_hired']);
        });
    }
};

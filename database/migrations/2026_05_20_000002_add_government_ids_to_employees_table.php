<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('gsis_number', 30)->nullable()->after('date_hired');
            $table->string('philhealth_number', 20)->nullable()->after('gsis_number');
            $table->string('pagibig_number', 20)->nullable()->after('philhealth_number');
            $table->string('tin_number', 20)->nullable()->after('pagibig_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['gsis_number', 'philhealth_number', 'pagibig_number', 'tin_number']);
        });
    }
};

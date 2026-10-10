<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// COS / Job Order employees print one DTR per half month, so a print consent
// now names which span it covers: 'full' (whole month — every existing row),
// 'first' (days 1–15) or 'second' (16th to month end). See App\Support\DtrPeriod.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_print_consents', function (Blueprint $table) {
            $table->string('period', 6)->default('full')->after('year');
            $table->index(['employee_id', 'year', 'month', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('dtr_print_consents', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'year', 'month', 'period']);
            $table->dropColumn('period');
        });
    }
};

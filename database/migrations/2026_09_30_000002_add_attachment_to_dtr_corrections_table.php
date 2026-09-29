<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_corrections', function (Blueprint $table) {
            $table->string('attachment', 255)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_corrections', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};

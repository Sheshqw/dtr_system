<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_corrections', function (Blueprint $table) {
            // Links multiple field corrections submitted together as one request
            $table->string('group_id', 36)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dtr_corrections', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-in now pauses for 5 minutes after 3 wrong passwords. Only moves
     * settings still on the old defaults, so a value a Super Admin chose
     * in System Settings stays as it is.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_login_attempts')->default(3)->change();
            $table->unsignedInteger('lockout_duration_minutes')->default(5)->change();
        });

        DB::table('system_settings')
            ->where('max_login_attempts', 5)
            ->where('lockout_duration_minutes', 30)
            ->update(['max_login_attempts' => 3, 'lockout_duration_minutes' => 5]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_login_attempts')->default(5)->change();
            $table->unsignedInteger('lockout_duration_minutes')->default(30)->change();
        });

        DB::table('system_settings')
            ->where('max_login_attempts', 3)
            ->where('lockout_duration_minutes', 5)
            ->update(['max_login_attempts' => 5, 'lockout_duration_minutes' => 30]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // How long a login token stays valid before requiring sign-in
            // again — mirrors the value previously hardcoded in config/sanctum.php.
            $table->unsignedInteger('session_timeout_minutes')->default(1440);

            // Password policy — mirrors the rule previously hardcoded in
            // AppServiceProvider (Password::min(8)->letters()->numbers()).
            // Letters are always required regardless of these settings; a
            // digits-only password is never allowed.
            $table->unsignedTinyInteger('password_min_length')->default(8);
            $table->boolean('password_require_number')->default(true);
            $table->boolean('password_require_symbol')->default(false);

            // Failed-login lockout — mirrors the values previously hardcoded
            // in AuthController::login().
            $table->unsignedTinyInteger('max_login_attempts')->default(5);
            $table->unsignedInteger('lockout_duration_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'session_timeout_minutes',
                'password_min_length',
                'password_require_number',
                'password_require_symbol',
                'max_login_attempts',
                'lockout_duration_minutes',
            ]);
        });
    }
};

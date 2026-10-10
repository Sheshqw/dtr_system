<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Require a Cloudflare Turnstile check on the login form. Off by
            // default — it also needs TURNSTILE_SITE_KEY/SECRET_KEY in .env
            // and internet access, so turning it on is a deliberate choice.
            $table->boolean('turnstile_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('turnstile_enabled');
        });
    }
};

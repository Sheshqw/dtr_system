<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // A campus photo shown behind the login page's navy overlay.
            // null = the bundled illustrated background.
            $table->string('login_background_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('login_background_path');
        });
    }
};

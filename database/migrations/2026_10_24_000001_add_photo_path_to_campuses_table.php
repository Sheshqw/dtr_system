<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            // A photo the Super Admin uploaded for the login page slideshow.
            // null = the bundled public/images/campuses/<CODE>.jpg, if any.
            $table->string('photo_path')->nullable()->after('director_name');
        });
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};

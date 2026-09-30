<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets a reviewer (admin/HR officer) upload their own digital signature and
// opt in/out of having it appear on DTRs they approve for employee download.
// See SignatureController and ReportController::form48().
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('signature_path', 255)->nullable()->after('photo_path');
            $table->boolean('signature_enabled')->default(false)->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signature_enabled']);
        });
    }
};

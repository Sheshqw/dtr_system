<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The HR Officer no longer signs CSC Form 48 — only the employee and the
// Campus Director do — so the reviewer's digital signature has nowhere to
// appear. Any images already uploaded stay in storage/app/public/signatures.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signature_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('signature_path', 255)->nullable()->after('photo_path');
            $table->boolean('signature_enabled')->default(false)->after('signature_path');
        });
    }
};

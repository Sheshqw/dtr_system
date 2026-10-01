<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// fingerprint_id is looked up on every single biometric device push
// (BiometricPushController::push(), BiometricController's LAN pull) — at
// 2,600+ employees that's a full table scan per punch without an index.
// Validated unique at the application layer already (EmployeeController);
// not made a DB-level unique constraint here since existing rows may
// already have duplicate/empty values that would block the migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('fingerprint_id', 'idx_emp_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_emp_fingerprint');
        });
    }
};

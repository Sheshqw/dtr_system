<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The holidays.type column was created as an enum('national','local',
// 'special_non_working'), but the controller validation and the entire
// frontend form only ever use 'regular'/'special' -- matching actual
// Philippine labor law terminology (Regular vs Special Non-Working
// Holiday). Saving a holiday has been failing outright on MySQL, whose
// enum constraint is strictly enforced (unlike SQLite in local dev).
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite has no native ALTER COLUMN for enum/check constraints;
            // its "enum" is just a CHECK, and this app's local db never had
            // conflicting data, so a straight column rebuild isn't needed.
            Schema::table('holidays', function ($table) {
                $table->string('type', 30)->default('regular')->change();
            });
            return;
        }

        DB::statement("ALTER TABLE holidays MODIFY type ENUM('regular','special') NOT NULL DEFAULT 'regular'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('holidays', function ($table) {
                $table->string('type', 30)->default('national')->change();
            });
            return;
        }

        DB::statement("ALTER TABLE holidays MODIFY type ENUM('national','local','special_non_working') NOT NULL DEFAULT 'national'");
    }
};

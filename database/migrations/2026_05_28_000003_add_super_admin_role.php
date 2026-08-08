<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB only — SQLite doesn't support ENUM or MODIFY COLUMN
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('super_admin','admin','hr_officer','department_head','employee') NOT NULL DEFAULT 'employee'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('admin','hr_officer','department_head','employee') NOT NULL DEFAULT 'employee'");
        }
    }
};

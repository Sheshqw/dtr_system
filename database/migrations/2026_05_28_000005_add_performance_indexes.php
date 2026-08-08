<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attendance_logs — queried heavily by employee + date
        $this->addIndex('attendance_logs', function (Blueprint $t) {
            $t->index(['employee_id', 'punch_time'], 'idx_al_emp_punch');
            $t->index('punch_time', 'idx_al_punch_time');
        });

        // daily_dtr — queried by employee + month, and by date + status for analytics
        $this->addIndex('daily_dtr', function (Blueprint $t) {
            $t->index(['employee_id', 'work_date'], 'idx_dtr_emp_date');
            $t->index('work_date', 'idx_dtr_work_date');
            $t->index(['work_date', 'status'], 'idx_dtr_date_status');
        });

        // employees — filtered by campus, department, role, active status
        $this->addIndex('employees', function (Blueprint $t) {
            $t->index(['campus_id', 'is_active'], 'idx_emp_campus_active');
            $t->index(['department_id', 'is_active'], 'idx_emp_dept_active');
            $t->index('role', 'idx_emp_role');
        });

        // audit_logs — filtered by date and action
        $this->addIndex('audit_logs', function (Blueprint $t) {
            $t->index('created_at', 'idx_audit_created');
            $t->index('action', 'idx_audit_action');
        });
    }

    public function down(): void
    {
        $drops = [
            'attendance_logs' => ['idx_al_emp_punch', 'idx_al_punch_time'],
            'daily_dtr'       => ['idx_dtr_emp_date', 'idx_dtr_work_date', 'idx_dtr_date_status'],
            'employees'       => ['idx_emp_campus_active', 'idx_emp_dept_active', 'idx_emp_role'],
            'audit_logs'      => ['idx_audit_created', 'idx_audit_action'],
        ];

        foreach ($drops as $table => $indexes) {
            try {
                Schema::table($table, function (Blueprint $t) use ($indexes) {
                    foreach ($indexes as $idx) {
                        $t->dropIndex($idx);
                    }
                });
            } catch (\Exception $e) {
                // Index may not exist
            }
        }
    }

    private function addIndex(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Exception $e) {
            // SQLite or index already exists — safe to skip
        }
    }
};

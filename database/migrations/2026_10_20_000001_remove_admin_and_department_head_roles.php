<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The system now has three roles: super_admin, hr_officer and employee.
// Campus Admins become HR Officers (they keep campus-level management, which
// HR now owns) and Department Heads become Faculty / Staff.
//
// The role column itself is left as-is — dropping enum values needs a table
// rebuild on SQLite and gains nothing, since the app only ever assigns the
// three remaining roles.
return new class extends Migration
{
    public function up(): void
    {
        // These were the seeded default positions for admin accounts. Left in
        // place, they'd print as the verifier title on CSC Form 48 (an HR
        // Officer's own position replaces "HR Officer" there), so clear them.
        DB::table('employees')
            ->where('role', 'admin')
            ->whereIn('position', ['Campus Administrator', 'System Administrator'])
            ->update(['position' => null]);

        DB::table('employees')->where('role', 'admin')->update(['role' => 'hr_officer']);
        DB::table('employees')->where('role', 'department_head')->update(['role' => 'employee']);
    }

    public function down(): void
    {
        // Not reversible: once merged, there's no record of which HR Officers
        // were Campus Admins or which employees were Department Heads.
    }
};

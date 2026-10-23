<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The Campus Director is the only verifying signatory on CSC Form 48 (the
// panel's revision dropped the HR Officer), so each campus keeps its own
// director's name — editable on the Campuses page instead of hard-coded in
// the PDF template.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->string('director_name', 120)->nullable()->after('address');
        });

        // The one name the template used to hard-code.
        DB::table('campuses')->where('code', 'CAN')->whereNull('director_name')
            ->update(['director_name' => 'JUANCHO A. INTANO, Ph.D.']);
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn('director_name');
        });
    }
};

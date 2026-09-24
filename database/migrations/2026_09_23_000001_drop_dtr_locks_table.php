<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DTR Month-Locking was cut from project scope; this cleans up the table
// left behind by the original create migration (which was removed rather
// than kept around for a feature that no longer exists in the app).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dtr_locks');
    }

    public function down(): void
    {
        Schema::create('dtr_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('locked_by')->constrained('employees');
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamps();
            $table->unique(['campus_id', 'year', 'month']);
        });
    }
};

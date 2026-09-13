<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('locked_by')->constrained('employees');
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamps();

            // A row existing = that campus's month is locked. Unlocking deletes
            // the row rather than flagging it, so re-locking the same month
            // later is just a fresh insert — history of both actions still
            // lives in the audit trail, not duplicated here.
            $table->unique(['campus_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_locks');
    }
};

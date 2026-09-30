<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An employee must get HR/Admin sign-off before they can obtain an official,
// printable copy of their own DTR for a given month — each approval is good
// for exactly one download (see used_at), so re-printing requires a fresh
// request. See DtrPrintConsentController and ReportController::form48().
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_print_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('purpose', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'month', 'year']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_print_consents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DTR Correction Requests is removed — fixing a wrong time entry is still
// possible through the existing manual time entry (Super Admin / Admin / HR
// Officer on the Attendance page, AttendanceController::storeLog/destroyLog),
// which never touched these columns. Confirmed via a full-repo grep that
// is_corrected/corrected_by/corrected_at/correction_reason were only ever
// written by the removed DtrCorrectionController::applyReview() — safe to
// drop rather than just stop using.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dtr_corrections');

        Schema::table('daily_dtr', function (Blueprint $table) {
            $table->dropForeign(['corrected_by']);
            $table->dropColumn(['is_corrected', 'corrected_by', 'corrected_at', 'correction_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_dtr', function (Blueprint $table) {
            $table->boolean('is_corrected')->default(false);
            $table->foreignId('corrected_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->text('correction_reason')->nullable();
        });

        Schema::create('dtr_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('group_id', 36)->nullable();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->enum('field', ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out']);
            $table->string('original_value', 8)->nullable();
            $table->string('requested_value', 8);
            $table->text('reason');
            $table->string('attachment', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }
};

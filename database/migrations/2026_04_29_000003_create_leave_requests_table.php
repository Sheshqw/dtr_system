<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('leave_type', ['SL', 'VL', 'EL', 'LWOP', 'OB', 'SPL', 'ML', 'PL']);
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedSmallInteger('total_days');
            $table->text('reason');
            $table->string('attachment', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('filed_at')->useCurrent();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

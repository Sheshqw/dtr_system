<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('severity', ['critical', 'warning', 'info'])->default('warning');
            $table->string('type', 60); // exception, db_integrity, data_integrity, ...
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->longText('trace')->nullable();
            $table->json('context')->nullable();

            // Hash of type+message+file+line — lets a recurring problem bump
            // `occurrences` on one row instead of flooding the table with duplicates.
            $table->string('signature', 64);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();

            $table->enum('status', ['new', 'acknowledged', 'resolved'])->default('new');
            $table->foreignId('acknowledged_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('signature');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_logs');
    }
};

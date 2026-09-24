<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('employees')->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->boolean('granted')->default(false);
            $table->foreignId('granted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_permissions');
    }
};

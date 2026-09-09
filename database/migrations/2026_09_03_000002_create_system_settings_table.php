<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single-row table — one global branding record for the whole
        // system (logo, name, tagline). Per-campus color identity already
        // lives elsewhere (useCampusIdentity); this is the system-wide brand.
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_name', 60)->default('NEMSU');
            $table->string('short_name', 30)->default('UDTRMS');
            $table->string('tagline', 150)->default('University Daily Time Record Monitoring System');
            $table->string('logo_path')->nullable(); // null = use the bundled default logo
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

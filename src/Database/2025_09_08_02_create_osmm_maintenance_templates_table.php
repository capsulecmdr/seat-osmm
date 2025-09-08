<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('osmm_maintenance_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();               // e.g., "DB Upgrades — short notice"
            $table->string('reason', 200);                  // short headline
            $table->text('description')->nullable();        // longer copy
            $table->boolean('is_active')->default(true);    // allow disabling a template
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osmm_maintenance_templates');
    }
};

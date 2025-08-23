<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Structure:
     * - Top-level (parents): parent = NULL, route_segment is usually set, route may be NULL.
     * - Children: parent = id of parent row, route_segment = NULL, route usually set.
     */
    public function up(): void
    {
        Schema::create('osmm_menu_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Hierarchy + ordering
            $table->unsignedBigInteger('parent')->nullable()->index(); // self-ref to id
            $table->unsignedInteger('order')->default(1)->comment('Display order within parent');

            // Display & behavior
            $table->string('name', 150)->nullable();
            $table->string('icon', 150)->nullable();

            // Matching keys
            $table->string('route_segment', 150)->nullable()->index(); // used for top-level matching
            $table->string('route', 190)->nullable()->index();         // used for child matching

            // Authorization
            $table->string('permission', 190)->nullable()->index();

            $table->timestamps();

            // Optional: self-referential FK (delete children if parent is removed)
            $table->foreign('parent')
                  ->references('id')
                  ->on('osmm_menu_items')
                  ->cascadeOnDelete();

            // Helpful composite indexes
            $table->index(['parent', 'order']);
            $table->index(['parent', 'route']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop FK first for some DBs (MySQL older versions)
        Schema::table('osmm_menu_items', function (Blueprint $table) {
            // Guard in case the FK name differs on some platforms
            try {
                $table->dropForeign(['parent']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::dropIfExists('osmm_menu_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Make `order` nullable (no default).
        // Preferred (requires doctrine/dbal):
        try {
            Schema::table('osmm_menu_items', function (Blueprint $table) {
                $table->unsignedInteger('order')->nullable()->comment('1-based position; NULL = follow native order')->change();
            });
        } catch (\Throwable $e) {
            // Fallback raw SQL for MySQL
            try {
                DB::statement("ALTER TABLE osmm_menu_items MODIFY `order` INT UNSIGNED NULL COMMENT '1-based position; NULL = follow native order'");
            } catch (\Throwable $ignored) {}
        }

        // 2) Add helper index on (parent, name) if missing
        $this->ensureIndex('osmm_menu_items', 'osmm_parent_name_idx', 'INDEX (`parent`, `name`)');

        // 3) Add unique on (parent, route) if missing
        $this->ensureIndex('osmm_menu_items', 'osmm_parent_route_unique', 'UNIQUE (`parent`, `route`)');
    }

    public function down(): void
    {
        // Rollback indexes safely
        $this->dropIndexIfExists('osmm_menu_items', 'osmm_parent_name_idx');
        $this->dropIndexIfExists('osmm_menu_items', 'osmm_parent_route_unique');

        // Revert `order` back to NOT NULL DEFAULT 1 (if you really need to)
        try {
            Schema::table('osmm_menu_items', function (Blueprint $table) {
                $table->unsignedInteger('order')->default(1)->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            try {
                DB::statement("ALTER TABLE osmm_menu_items MODIFY `order` INT UNSIGNED NOT NULL DEFAULT 1");
            } catch (\Throwable $ignored) {}
        }
    }

    private function ensureIndex(string $table, string $name, string $ddlTail): void
    {
        // Works for MySQL
        $has = DB::selectOne("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);
        if (!$has) {
            DB::statement("ALTER TABLE `{$table}` ADD {$ddlTail} /*!50100 KEY_BLOCK_SIZE=8 */ , ALGORITHM=INPLACE, LOCK=NONE");
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        $has = DB::selectOne("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);
        if ($has) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
        }
    }
};

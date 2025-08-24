<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('osmm_menu_items', function (Blueprint $table) {
            // null = no override; 1 = force show; 0 = force hide
            $table->boolean('visible')->nullable()
                  ->after('permission')
                  ->comment('null=no override; 1=show; 0=hide');

            $table->index('visible');
        });
    }

    public function down(): void
    {
        Schema::table('osmm_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('osmm_menu_items', 'visible')) {
                $table->dropIndex(['visible']);
                $table->dropColumn('visible');
            }
        });
    }
};

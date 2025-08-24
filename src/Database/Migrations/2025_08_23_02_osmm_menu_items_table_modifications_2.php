<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('osmm_menu_items', function (Blueprint $table) {
            // Display override fields (NULL => keep native)
            $table->string('name_override', 150)
                  ->nullable()
                  ->after('name')
                  ->comment('Display name/label override; NULL = keep native');

            // Optional: if you prefer overriding the translation key itself
            $table->string('label_override', 190)
                  ->nullable()
                  ->after('name_override')
                  ->comment('Optional translation key override; NULL = keep native');
        });
    }

    public function down(): void
    {
        Schema::table('osmm_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('osmm_menu_items', 'label_override')) {
                $table->dropColumn('label_override');
            }
            if (Schema::hasColumn('osmm_menu_items', 'name_override')) {
                $table->dropColumn('name_override');
            }
        });
    }
};

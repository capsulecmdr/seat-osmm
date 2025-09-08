<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('osmm_menu_overrides', function (Blueprint $t) {
            $t->id();
            $t->string('item_key')->unique();
            $t->string('label_override')->nullable();
            $t->string('permission_override')->nullable();
            $t->string('icon_override')->nullable();
            $t->string('route_override')->nullable();
            $t->tinyInteger('visible')->nullable();      // null=no change, 0=hide, 1=force show
            $t->integer('order_override')->nullable();   // 1-based
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('osmm_menu_overrides');
    }
};

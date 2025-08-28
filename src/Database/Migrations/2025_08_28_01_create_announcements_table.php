<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('osmm_announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->text('content');                     // HTML allowed (sanitize on save if desired)
            $t->enum('status', ['new','active','expired'])->default('new');
            $t->timestamp('starts_at')->nullable();  // optional schedule window
            $t->timestamp('ends_at')->nullable();    // optional schedule window
            $t->boolean('show_banner')->default(true);
            $t->boolean('send_to_discord')->default(false);
            $t->timestamps();
            $t->index(['status','starts_at','ends_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('osmm_announcements');
    }
};

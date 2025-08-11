<?php 
// database/migrations/2025_08_11_01_create_osmm_todos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('osmm_todos', function (Blueprint $table) {
            $table->bigIncrements('id');           // our own PK is fine as BIGINT
            $table->unsignedInteger('user_id');    // << match SeAT users.id type
            $table->string('text', 200);
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('osmm_todos');
    }
};

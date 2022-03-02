<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('name', 20); // Kodowane automatycznie
            $table->enum('default_avatar', ['AVATAR_1', 'AVATAR_2', 'AVATAR_3', 'AVATAR_4', 'AVATAR_5']);
            $table->string('producer', 30)->nullable();
            $table->string('model', 50)->nullable();
            $table->enum('os_name', ['ANDROID', 'IOS'])->nullable();
            $table->string('os_version', 10)->nullable();
            $table->enum('app_version', ['1.0.0'])->default('1.0.0');
            $table->char('uuid', 60)->nullable(); // Kodowane automatycznie
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('users');
    }
};

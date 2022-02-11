<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->char('name', 20); // Kodowane automatycznie
            $table->enum('default_avatar', ['Avatar 1', 'Avatar 2', 'Avatar 3', 'Avatar 4', 'Avatar 5']);
            $table->string('producer', 30);
            $table->string('model', 50);
            $table->enum('os_name', ['Android', 'iOS']);
            $table->string('os_version', 10);
            $table->enum('app_version', ['1.0.0']);
            $table->char('uuid', 60); // Kodowane automatycznie
            $table->char('token', 32)->unique(); // Kodowane automatycznie
            $table->char('refresh_token', 32)->unique(); // Kodowane automatycznie
            $table->timestamp('tokens_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('devices');
    }
}

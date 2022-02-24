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
            $table->string('producer', 30)->nullable();
            $table->string('model', 50)->nullable();
            $table->enum('os_name', ['Android', 'iOS'])->nullable();
            $table->string('os_version', 10)->nullable();
            $table->enum('app_version', ['1.0.0'])->default('1.0.0');
            $table->char('uuid', 60)->nullable(); // Kodowane automatycznie
            $table->char('token', 32)->unique(); // Kodowane automatycznie
            $table->char('refresh_token', 32)->unique(); // Kodowane automatycznie
            $table->timestamp('token_expires_at')->nullable();
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

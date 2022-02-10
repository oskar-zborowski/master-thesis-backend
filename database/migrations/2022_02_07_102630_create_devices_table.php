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
            $table->tinyInteger('default_avatar');
            $table->string('model', 50);
            $table->string('os_name', 10);
            $table->string('os_version', 10);
            $table->string('app_version', 10);
            $table->char('uuid', 60); // Kodowane automatycznie
            $table->char('ip_address', 60); // Kodowane automatycznie
            $table->char('token', 32)->unique(); // Kodowane automatycznie
            $table->char('refresh_token', 32)->unique(); // Kodowane automatycznie
            $table->unsignedBigInteger('request_counter')->default(0);
            $table->timestamp('last_request_at')->nullable();
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

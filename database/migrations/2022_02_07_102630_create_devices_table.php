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
            $table->integerIncrements('id');
            $table->char('uuid', 64)->unique(); // Kodowane natywnie
            $table->char('ip', 20); // Kodowane natywnie
            $table->char('name', 40); // Kodowane natywnie
            $table->char('avatar', 64); // Kodowane natywnie
            $table->char('os_name', 40); // Kodowane natywnie
            $table->char('os_version', 40); // Kodowane natywnie
            $table->char('app_version', 40); // Kodowane natywnie
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

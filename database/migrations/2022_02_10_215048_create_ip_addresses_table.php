<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIpAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->char('ip_address', 60); // Kodowane automatycznie
            $table->unsignedBigInteger('request_counter')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('ip_addresses');
    }
}

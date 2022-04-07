<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('gps_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->point('gps_location');
            $table->string('street', 80);
            $table->string('city', 40);
            $table->string('voivodeship', 20);
            $table->string('country', 30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('gps_logs');
    }
};

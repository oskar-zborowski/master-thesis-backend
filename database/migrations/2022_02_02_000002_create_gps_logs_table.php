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
            $table->char('gps_location', 80); // Kodowane automatycznie | Struktura: -179.12345 -89.12345
            $table->char('house_number', 48)->nullable(); // Kodowane automatycznie
            $table->char('street', 176)->nullable(); // Kodowane automatycznie
            $table->char('housing_estate', 176)->nullable(); // Kodowane automatycznie
            $table->char('district', 176)->nullable(); // Kodowane automatycznie
            $table->char('city', 112)->nullable(); // Kodowane automatycznie
            $table->char('voivodeship', 80)->nullable(); // Kodowane automatycznie
            $table->char('country', 80)->nullable(); // Kodowane automatycznie
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

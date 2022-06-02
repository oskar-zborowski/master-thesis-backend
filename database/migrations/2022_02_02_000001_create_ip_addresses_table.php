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
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->char('ip_address', 112)->unique(); // Kodowane automatycznie
            $table->char('provider', 208)->nullable(); // Kodowane automatycznie
            $table->char('city', 208)->nullable(); // Kodowane automatycznie
            $table->char('voivodeship', 208)->nullable(); // Kodowane automatycznie
            $table->char('country', 144)->nullable(); // Kodowane automatycznie
            $table->boolean('is_mobile')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('ip_addresses');
    }
};

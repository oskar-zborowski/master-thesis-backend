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
        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('nominatim_is_busy')->default(false);
            $table->boolean('ip_api_is_busy')->default(false);
            $table->boolean('mail_is_busy')->default(false);
            $table->timestamp('nominatim_last_used_at')->nullable();
            $table->timestamp('ip_api_last_used_at')->nullable();
            $table->timestamp('mail_last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('configs');
    }
};

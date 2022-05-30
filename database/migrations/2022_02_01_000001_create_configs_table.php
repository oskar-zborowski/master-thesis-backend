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
            $table->unsignedBigInteger('log_counter')->default(0);
            $table->boolean('is_nominatim_busy')->default(false);
            $table->boolean('is_ip_api_busy')->default(false);
            $table->boolean('is_mail_busy')->default(false);
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

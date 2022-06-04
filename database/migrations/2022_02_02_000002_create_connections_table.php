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
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignId('ip_address_id')->nullable()->references('id')->on('ip_addresses')->nullOnDelete();
            $table->unsignedBigInteger('successful_request_counter')->default(0);
            $table->unsignedBigInteger('failed_request_counter')->default(0);
            $table->unsignedBigInteger('malicious_request_counter')->default(0);
            $table->unsignedBigInteger('crawler_request_counter')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('connections');
    }
};

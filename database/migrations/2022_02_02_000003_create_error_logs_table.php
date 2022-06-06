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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->nullable()->unique();
            $table->foreignId('connection_id')->nullable()->references('id')->on('connections')->nullOnDelete();
            $table->string('type', 30);
            $table->string('thrower', 100);
            $table->string('file', 150)->nullable();
            $table->string('method', 50)->nullable();
            $table->unsignedSmallInteger('line')->nullable();
            $table->string('subject', 100);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('error_logs');
    }
};

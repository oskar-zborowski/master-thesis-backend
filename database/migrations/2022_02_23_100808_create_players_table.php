<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->references('id')->on('devices')->nullable()->nullOnDelete();
            $table->foreignId('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->enum('avatar', ['Avatar 1', 'Avatar 2', 'Avatar 3', 'Avatar 4', 'Avatar 5']);
            $table->enum('role', ['policeman', 'thief', 'agent', 'saboteur']);
            $table->enum('direction', ['North-South', 'East-West'])->nullable();
            $table->multiLineString('track')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->unsignedTinyInteger('fake_position_used_number')->default(0);
            $table->unsignedTinyInteger('black_ticket_used_number')->default(0);
            $table->unsignedTinyInteger('white_ticket_used_number')->default(0);
            $table->timestamp('catching_finished_at')->nullable();
            $table->timestamp('mission_finished_at')->nullable();
            $table->enum('status', ['IN GAME', 'CAUGHT'])->default('IN GAME');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('players');
    }
}

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
            $table->float('bot_physical_endurance')->default(1);
            $table->enum('direction', ['North-South', 'East-West'])->nullable();
            $table->multiLineString('track')->nullable();
            $table->multiLineString('disclosure_track')->nullable();
            $table->json('player_config');
            $table->multiPoint('missions_completed')->nullable();
            $table->timestamp('catching_finished_at')->nullable();
            $table->timestamp('mission_finished_at')->nullable();
            $table->enum('status', ['IN ROOM', 'IN GAME', 'CAUGHT', 'DISCONNECTED', 'BORDER_CROSSED'])->default('IN ROOM');
            $table->boolean('is_disclosed')->default(1);
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

// Struktura JSONa z domyślnymi wartościami dla pola "player_config"
//     "ticket": {
//         "black": {
//             "number": 0,
//             "used_number": 0
//         },
//         "white": {
//             "number": 0,
//             "used_number": 0
//         },
//         "gold": {
//             "number": 0,
//             "used_number": 0
//         },
//         "silver": {
//             "number": 0,
//             "used_number": 0
//         }
//     },
//     "fake_position": {
//         "number": 0,
//         "used_number": 0
//     }

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->references('id')->on('devices')->nullable()->nullOnDelete();
            $table->char('code', 8); // Kodowane automatycznie
            $table->unsignedSmallInteger('game_counter');
            $table->enum('game_mode', ['Scotland Yard', 'Mission: Impossible'])->default('Scotland Yard');
            $table->json('game_config');
            $table->polygon('boundary')->nullable();
            $table->multiPoint('mission_centers')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->multiPoint('monitoring_centers')->nullable();
            $table->multiPoint('monitoring_centrals')->nullable();
            $table->timestamp('game_started_at')->nullable();
            $table->timestamp('game_paused_at')->nullable();
            $table->timestamp('game_ended_at')->nullable();
            $table->timestamp('disclosure_at')->nullable();
            $table->enum('status', ['WAITING IN ROOM', 'GAME IN PROGRESS', 'GAME PAUSED', 'GAME OVER'])->default('WAITING IN ROOM');
            $table->enum('game_result', ['THIEVES WON ON TIME', 'POLICEMEN WON ON TIME', 'POLICEMEN WON BY CATCHING', 'THIEVES WON BY COMPLETING MISSIONS'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('rooms');
    }
}

// Struktura JSONa pola "game_config"
// {
//     "game_duration": {
//         "scheduled": 1800,
//         "real": 0
//     },
//     "escape_time": 300,
//     "actor": {
//         "policeman" : {
//             "number": 5
//         },
//         "thief": {
//             "number": 1
//         },
//         "agent": {
//             "number": 0
//         },
//         "saboteur": {
//             "number": 0,
//             "probability": 0
//         }
//     },
//     "disclosure": {
//         "interval": 180,
//         "impulses_number": 10,
//         "remaining_impulses_number": 10
//     },
//     "catching": {
//         "catchers_number": 2,
//         "radius": 50,
//         "time": 5
//     },
//     "mission": {
//         "number": 5,
//         "radius": 50,
//         "time": 10
//     },
//     "monitoring": {
//         "number": 0,
//         "radius": 50,
//         "central": {
//             "number": 0,
//             "radius": 50
//         }
//     },
//     "ticket": {
//         "black": {
//             "number": 0,
//             "used_number": 0
//         },
//         "white": {
//             "number": 0,
//             "used_number": 0
//         }
//     },
//     "fake_position": {
//         "number": 0,
//         "used_number": 0,
//         "radius": 250
//     },
//     "other": {
//         "is_role_random": true,
//         "is_thief_direction_visible": true,
//         "is_thief_disclosure_visible": true,
//         "is_catching_visible": true,
//         "is_agent_visible": true,
//         "is_game_paused_when_disconnected": true,
//         "is_game_paused_after_crossing_border": true,
//         "is_position_shown_after_crossing_border": false
//     }
// }

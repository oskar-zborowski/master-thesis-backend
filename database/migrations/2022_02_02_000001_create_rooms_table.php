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
            $table->string('street', 80)->nullable();
            $table->string('city', 40)->nullable();
            $table->string('voivodeship', 20)->nullable();
            $table->string('country', 30)->nullable();
            $table->unsignedSmallInteger('game_counter');
            $table->enum('game_mode', ['SCOTLAND_YARD', 'MISSION_IMPOSSIBLE'])->default('SCOTLAND_YARD');
            $table->json('game_config');
            $table->polygon('boundary')->nullable();
            $table->multiPoint('mission_centers')->nullable();
            $table->multiPoint('monitoring_centers')->nullable();
            $table->multiPoint('monitoring_centrals')->nullable();
            $table->enum('status', ['WAITING_IN_ROOM', 'GAME_IN_PROGRESS', 'GAME_PAUSED', 'GAME_OVER'])->default('WAITING_IN_ROOM');
            $table->enum('game_result', ['POLICEMEN_WON_BY_CATCHING', 'POLICEMEN_WON_ON_TIME', 'THIEVES_WON_BY_COMPLETING_MISSIONS', 'THIEVES_WON_ON_TIME'])->nullable();
            $table->timestamp('game_started_at')->nullable();
            $table->timestamp('game_paused_at')->nullable();
            $table->timestamp('game_ended_at')->nullable();
            $table->timestamp('next_disclosure_at')->nullable();
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

// Struktura JSONa z domyślnymi wartościami dla pola "game_config"
// {
//     "actor": {
//         "policeman": {
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
//             "probability": 0.5
//         }
//     },
//     "bot": {
//         "policeman": {
//             "maximum_speed": 4,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "thief": {
//             "maximum_speed": 4,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "agent": {
//             "maximum_speed": 4,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "saboteur": {
//             "maximum_speed": 4,
//             "physical_endurance": 0.8,
//             "level": 2
//         }
//     },
//     "game_duration": {
//         "scheduled": 1800,
//         "real": 0
//     },
//     "escape": {
//         "time": 300
//     },
//     "catching": {
//         "catchers_number": 2,
//         "radius": 50,
//         "time": 5
//     },
//     "disclosure": {
//         "interval": 180,
//         "after_starting": false,
//         "thief_direction": true,
//         "short_distance": true,
//         "thief_knows_when": true,
//         "agent": true,
//         "agent_knows_when": true,
//         "after_crossing_border": false
//     },
//     "monitoring": {
//         "number": 0,
//         "radius": 50,
//         "central": {
//             "number": 0,
//             "radius": 50
//         }
//     },
//     "mission": {
//         "number": 5,
//         "radius": 50,
//         "time": 10
//     },
//     "ticket": {
//         "black": {
//             "number": 0,
//             "probability": 0.5
//         },
//         "white": {
//             "number": 0,
//             "probability": 0.5
//         },
//         "gold": {
//             "number": 0,
//             "probability": 0.5
//         },
//         "silver": {
//             "number": 0,
//             "probability": 0.5
//         }
//     },
//     "fake_position": {
//         "number": 0,
//         "probability": 0.5,
//         "radius": 250
//     },
//     "game_pause": {
//         "after_disconnecting": true,
//         "after_crossing_border": true
//     },
//     "other": {
//         "role_random": true,
//         "thief_knows_who_is_saboteur": true,
//         "saboteur_sees_thief": true
//     }
// }

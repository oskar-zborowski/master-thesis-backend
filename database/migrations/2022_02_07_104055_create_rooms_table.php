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
            $table->foreignId('supervisor_id')->references('id')->on('devices')->nullable()->nullOnDelete();
            $table->char('code', 8); // Kodowane automatycznie
            $table->string('street', 80)->nullable();
            $table->string('city', 40)->nullable();
            $table->string('voivodeship', 20)->nullable();
            $table->string('country', 30)->nullable();
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
            $table->timestamp('next_disclosure_at')->nullable();
            $table->timestamp('last_calculation_at')->nullable();
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

// Struktura JSONa z domyślnymi wartościami dla pola "game_config"
// {
//     "actor": {
//         "policeman" : {
//             "number": 5,
//             "probability": 1
//         },
//         "thief": {
//             "number": 1,
//             "probability": 1
//         },
//         "agent": {
//             "number": 0,
//             "probability": 1
//         },
//         "saboteur": {
//             "number": 0,
//             "probability": 0.5
//         }
//     },
//     "bot": {
//         "policeman": {
//             "maximum_speed": 2.5,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "thief": {
//             "maximum_speed": 2.5,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "agent": {
//             "maximum_speed": 2.5,
//             "physical_endurance": 0.8,
//             "level": 2
//         },
//         "saboteur": {
//             "maximum_speed": 2.5,
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
//         "impulses_number": 3,
//         "remaining_impulses_number": 3,
//         "after_starting": false,
//         "thief_direction": true,
//         "short_distance": true,
//         "thief_knows_when": true,
//         "agent": true,
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
//             "used_number": 0 // przenieść do migracji player
//         },
//         "white": {
//             "number": 0,
//             "used_number": 0 // przenieść do migracji player
//         }
//     },
//     "fake_position": {
//         "number": 0,
//         "probability": 0.5,
//         "used_number": 0, // przenieść do migracji player
//         "radius": 250
//     },
//     "game_pause": {
//         "after_disconnecting": true,
//         "after_crossing_border": true
//     },
//     "other": {
//         "role_random": true,
//         "thief_knows_who_is_saboteur": true
//         "saboteur_sees_thief": true
//     }
// }

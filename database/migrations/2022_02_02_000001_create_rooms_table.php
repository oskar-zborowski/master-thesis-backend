<?php

use App\Http\Libraries\Validation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->char('code', 48)->unique(); // Kodowane automatycznie
            $table->string('street', 80)->nullable();
            $table->string('city', 40)->nullable();
            $table->string('voivodeship', 20)->nullable();
            $table->string('country', 30)->nullable();
            $table->enum('game_mode', Validation::getGameModes())->default(Validation::getGameModes()[0]);
            $table->json('game_config');
            $table->polygon('boundary')->nullable();
            $table->multiPolygon('missions')->nullable();
            $table->multiPolygon('monitoring_cameras')->nullable();
            $table->multiPolygon('monitoring_centrals')->nullable();
            $table->enum('status', Validation::getRoomStatuses())->default(Validation::getRoomStatuses()[0]);
            $table->enum('game_result', Validation::getGameResults())->nullable();
            $table->timestamp('game_started_at')->nullable();
            $table->timestamp('game_ended_at')->nullable();
            $table->timestamp('next_disclosure_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('rooms');
    }
};

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
//     "game_duration": {
//         "scheduled": 3600,
//         "escape_time": 600,
//         "real": 0
//     },
//     "catching": {
//         "number": 3,
//         "radius": 100,
//         "time": 10
//     },
//     "disclosure": {
//         "interval": 300,
//         "after_starting": false,
//         "thief_direction": false,
//         "short_distance": true,
//         "thief_knows_when": true,
//         "thief_knows_saboteur": false,
//         "saboteur_sees_thief": false,
//         "after_crossing_border": false
//     },
//     "mission": {
//         "number": 5,
//         "radius": 50,
//         "time": 10,
//         "all_visible": true
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
//             "probability": 0.5
//         },
//         "white": {
//             "number": 0,
//             "probability": 0.5
//         }
//     },
//     "fake_position": {
//         "number": 0,
//         "probability": 0.5
//     },
//     "game_pause": {
//         "after_disconnecting": true,
//         "after_crossing_border": false
//     },
//     "other": {
//         "role_random": true,
//         "warning_number": 2,
//         "max_speed": 6,
//         "bot_speed": 2.5
//     }
// }

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
            $table->char('gps_location', 80)->nullable(); // Kodowane automatycznie
            $table->char('house_number', 48)->nullable(); // Kodowane automatycznie
            $table->char('street', 176)->nullable(); // Kodowane automatycznie
            $table->char('housing_estate', 176)->nullable(); // Kodowane automatycznie
            $table->char('district', 176)->nullable(); // Kodowane automatycznie
            $table->char('city', 112)->nullable(); // Kodowane automatycznie
            $table->char('voivodeship', 80)->nullable(); // Kodowane automatycznie
            $table->char('country', 80)->nullable(); // Kodowane automatycznie
            $table->enum('game_mode', Validation::getGameModes())->default(Validation::getGameModes()[0]);
            $table->json('game_config');
            $table->char('boundary_points', 29936)->nullable(); // Kodowane automatycznie | Przykładowa struktura: lat:lng,lat:lng,lat:lng,lat:lng;lat:lng,lat:lng,lat:lng,lat:lng;lat:lng,lat:lng,lat:lng,lat:lng
            $table->polygon('boundary_polygon')->nullable(); // Poligon zewnętrzny może składać się z maksymalnie 50 pkt., może zawierać maksymalnie 30 poligonów wewnętrznych, z których każdy może posiadać maksymalnie 20 pkt.
            $table->char('mission_points', 2320)->nullable(); // Kodowane automatycznie | Przykładowa struktura: lat:lng,lat:lng,lat:lng
            $table->multiPolygon('mission_polygons')->nullable();
            $table->char('monitoring_camera_points', 496)->nullable(); // Kodowane automatycznie
            $table->multiPolygon('monitoring_camera_polygons')->nullable();
            $table->char('monitoring_central_points', 272)->nullable(); // Kodowane automatycznie
            $table->multiPolygon('monitoring_central_polygons')->nullable();
            $table->boolean('geometries_confirmed')->default(false);
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
//         "policeman_sees_agent": true,
//         "saboteur_sees_thief": false,
//         "thief_knows_saboteur": false,
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
//         "warning_number": 2,
//         "crossing_border_countdown": 30,
//         "max_speed": 6,
//         "bot_speed": 2.5
//     }
// }

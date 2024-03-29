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
            $table->foreignId('reporting_user_id')->nullable()->references('id')->on('users')->nullOnDelete(); // Użytkownik rozpoczynający głosowanie
            $table->char('group_code', 48); // Kodowane automatycznie | Potrzebne do znalezienia wszystkich pokoi w ramach jednego ciągu rozgrywek
            $table->char('code', 48); // Kodowane automatycznie
            $table->unsignedTinyInteger('counter')->default(1);
            $table->char('gps_location', 80)->nullable(); // Kodowane automatycznie | Struktura: -179.12345 -89.12345
            $table->char('house_number', 48)->nullable(); // Kodowane automatycznie
            $table->char('street', 176)->nullable(); // Kodowane automatycznie
            $table->char('housing_estate', 176)->nullable(); // Kodowane automatycznie
            $table->char('district', 176)->nullable(); // Kodowane automatycznie
            $table->char('city', 112)->nullable(); // Kodowane automatycznie
            $table->char('voivodeship', 80)->nullable(); // Kodowane automatycznie
            $table->char('country', 80)->nullable(); // Kodowane automatycznie
            $table->json('config');
            $table->string('boundary_points', 880)->nullable(); // Kodowane automatycznie | Obowiązuje wyłącznie jeden poligon, który może składać się z maksymalnie 20 pkt. | Struktura: -179.12345 -89.12345,-179.12345 -89.12345,-179.12345 -89.12345,-179.12345 -89.12345 | Usuwane po zakończeniu gry
            $table->enum('status', Validation::getRoomStatuses())->default('WAITING_IN_ROOM');
            $table->enum('game_result', Validation::getGameResults())->nullable();
            $table->enum('voting_type', Validation::getVotingTypes())->nullable();
            $table->timestamp('game_started_at')->nullable();
            $table->timestamp('game_ended_at')->nullable();
            $table->timestamp('next_disclosure_at')->nullable();
            $table->timestamp('voting_ended_at')->nullable();
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

// Struktura JSONa z domyślnymi wartościami dla pola "config"
// {
//     "actor": {
//         "policeman": {
//             "number": 5,
//             "visibility_radius": -1,
//             "are_friends_circles_visible": true,
//             "catching": {
//                 "number": 3,
//                 "radius": 100,
//             }
//         },
//         "thief": {
//             "number": 1,
//             "probability": 1,
//             "visibility_radius": -1,
//             "are_friends_circles_visible": true,
//             "are_enemies_circles_visible": true,
//             "escape_duration": 300,
//             "disclosure_interval": 300,
//             "black_ticket": {
//                 "number": 0,
//                 "probability": 0.5,
//                 "duration": 300
//             },
//             "fake_position": {
//                 "number": 0,
//                 "probability": 0.5,
//                 "duration": 300
//             }
//         },
//         "agent": {
//             "number": 0,
//             "probability": 0.5
//         },
//         "pegasus": {
//             "number": 0,
//             "probability": 0.5,
//             "white_ticket": {
//                 "number": 0,
//                 "probability": 0.5
//             }
//         },
//         "fatty_man": {
//             "number": 0,
//             "probability": 0.5
//         },
//         "eagle": {
//             "number": 0,
//             "probability": 0.5
//         }
//     },
//     "duration": {
//         "scheduled": 3600,
//         "real": 0
//     },
//     "other": {
//         "bot_speed": 2.5,
//         "max_speed": 15,
//         "warning_number": 2,
//         "is_pause_after_disconnecting": true,
//         "disconnecting_countdown": 60,
//         "crossing_boundary_countdown": 60
//     }
// }

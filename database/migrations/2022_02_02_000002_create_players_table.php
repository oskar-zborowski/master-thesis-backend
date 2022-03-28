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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->enum('avatar', Validation::getAvatars());
            $table->enum('role', Validation::getPlayerRoles())->nullable();
            $table->json('player_config');
            $table->json('thief_track')->nullable();
            $table->lineString('track')->nullable();
            $table->point('disclosed_thief_position')->nullable(); // pozycja złodzieja ujawniona poprzez standardowe ujawnianie (widoczna dla wszystkich)
            $table->point('thief_fake_position')->nullable(); // fake'owa pozycja złodzieja, po zużyciu jest usuwana
            $table->multiPoint('detected_thief_position')->nullable(); // pozycja złodzieja ujawniona poprzez np. wykrycie kamery albo wykorzystanie ticketu (przypisana do rekordu gracza, który pozycję wykrył). Jeżeli pozycja disclosed_thief_position będzie świeższa, u wszystkich graczy to pole jest usuwane
            $table->point('mission_performed')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->float('direction')->default(0);
            $table->unsignedTinyInteger('hide_stock')->default(0); // określa ile odkryć w przód złodziej ma ochronę przed ujawnieniem pozycji
            $table->boolean('is_bot')->default(false);
            $table->enum('status', Validation::getPlayerStatuses())->nullable();
            $table->unsignedTinyInteger('warning_number')->default(0);
            $table->unsignedSmallInteger('average_ping')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('standard_deviation')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('samples_number')->default(0);
            $table->timestamp('expected_time_at')->nullable();
            $table->timestamp('mission_finished_at')->nullable();
            $table->timestamp('catching_finished_at')->nullable();
            $table->timestamp('caught_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::dropIfExists('players');
    }
};

// Struktura JSONa z domyślnymi wartościami dla pola "player_config"
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
//         "used_number": 0
//     }

// Struktura JSONa z przykładowymi wartościami dla pola "disclosure_by_players"
//     0: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "hidden",
//     },
//     1: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "disclosure",
//         "is_fake_position": true,
//         "players": all
//     },
//     2: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "camera_detection",
//         "is_fake_position": false,
//         "players": {
//             15,
//             49
//         }
//     },
//     3: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "white_ticket",
//         "is_fake_position": true,
//         "players": {
//             7
//         }
//     },
//     4: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "black_ticket",
//         "is_fake_position": false,
//         "players": {
//             18
//         }
//     },
//     5: {
//         "position": {
//              "latitude": 17.5437434,
//              "longitude": 51.694656
//         },
//         "type": "crossing_border",
//         "is_fake_position": false,
//         "players": {
//             1,
//             3
//         }
//     }

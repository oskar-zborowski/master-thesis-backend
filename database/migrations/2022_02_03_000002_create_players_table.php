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
            $table->json('track')->nullable();
            $table->json('disclosures')->nullable(); // pozycje złodziei ujawnione poprzez np. wykrycie kamery albo wykorzystanie ticketu (przypisane do rekordu gracza, który pozycje wykrył). Jeżeli pozycja disclosed_position będzie świeższa, u wszystkich graczy to pole jest usuwane
            $table->json('missions_completed')->nullable();
            $table->point('disclosed_position')->nullable(); // pozycja gracza ujawniona dla wszystkich
            $table->point('thief_fake_position')->nullable(); // fake'owa pozycja złodzieja, po zużyciu jest usuwana
            $table->float('direction')->default(0);
            $table->unsignedTinyInteger('mission_performed')->nullable();
            $table->unsignedTinyInteger('hide_stock')->default(0); // określa ile odkryć w przód złodziej ma ochronę przed ujawnieniem pozycji
            $table->boolean('protected_disclosure')->default(false); // określa czy złodziej cały czas znajduje się w miejscu, które powinno ujawnić jego pozycję, gdyby nie black_ticket
            $table->boolean('is_bot')->default(false);
            $table->enum('status', Validation::getPlayerStatuses())->default(Validation::getPlayerStatuses()[0]);
            $table->enum('action_status', Validation::getActionStatuses())->nullable();
            $table->unsignedTinyInteger('warning_number')->default(0);
            $table->unsignedSmallInteger('average_ping')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('standard_deviation')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('samples_number')->default(0);
            $table->timestamp('expected_time_at')->nullable();
            $table->timestamp('crossing_border_finished_at')->nullable();
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

// Struktura JSONa z przykładowymi wartościami dla pola "track"
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "standard",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": false,
//         "players_id": null
//     },
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "disclosure",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": true,
//         "players_id": null
//     },
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "camera_detection",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": false,
//         "players_id": {
//             15,
//             49
//         }
//     },
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "white_ticket",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": true,
//         "players_id": {
//             18
//         }
//     },
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "black_ticket",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": false,
//         "players_id": {
//             18
//         }
//     },
//     {
//         "time": "2022-03-31 09:15:45",
//         "type": "crossing_border",
//         "position": {
//              "latitude": 51.6946562, // Kodowane automatycznie
//              "longitude": 17.5437434 // Kodowane automatycznie
//         },
//         "is_fake_position": false,
//         "players_id": {
//             15,
//             49
//         }
//     }

// Struktura JSONa z przykładowymi wartościami dla pola "disclosures"
//     {
//         "player_id": 3,
//         "position": {
//              "latitude": 51.6946562,
//              "longitude": 17.5437434
//         }
//     },
//     {
//         "player_id": 5,
//         "position": {
//              "latitude": 51.6946562,
//              "longitude": 17.5437434
//         }
//     }

// Struktura JSONa z przykładowymi wartościami dla pola "missions_completed"
//     {
//         1,
//         3
//     }

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
            $table->json('player_config')->nullable();
            $table->json('track')->nullable();
            $table->point('position')->nullable(); // ostatnia pozycja gracza ujawniona dla wszystkich (zarówno policjanta jak i złodzieja)
            $table->point('thief_fake_position')->nullable(); // fake'owa pozycja złodzieja, po wygaśnięciu jest usuwana
            $table->boolean('is_bot')->default(false);
            $table->boolean('crossing_border')->default(false);
            $table->enum('status', Validation::getPlayerStatuses())->default(Validation::getPlayerStatuses()[0]);
            $table->unsignedTinyInteger('warning_number')->default(0);
            $table->unsignedSmallInteger('average_ping')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('standard_deviation')->default(0); // wyrażone w [ms]
            $table->unsignedSmallInteger('samples_number')->default(0);
            $table->timestamp('expected_time_at')->nullable();
            $table->timestamp('disconnecting_finished_at')->nullable();
            $table->timestamp('crossing_border_finished_at')->nullable();
            $table->timestamp('black_ticket_finished_at')->nullable();
            $table->timestamp('fake_position_finished_at')->nullable();
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
//     "black_ticket": {
//         "number": 0,
//         "used_number": 0
//     },
//     "fake_position": {
//         "number": 0,
//         "used_number": 0
//     }
//
//     LUB
//
//     "white_ticket": {
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

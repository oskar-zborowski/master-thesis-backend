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
            $table->foreignId('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->references('id')->on('users')->nullable()->nullOnDelete();
            $table->enum('avatar', ['AVATAR_1', 'AVATAR_2', 'AVATAR_3', 'AVATAR_4', 'AVATAR_5']);
            $table->enum('role', ['POLICEMAN', 'THIEF', 'AGENT', 'SABOTEUR'])->nullable();
            $table->json('player_config');
            $table->multiLineString('track')->nullable();
            $table->multiLineString('disclosure_track')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->float('direction')->default(0);
            $table->unsignedTinyInteger('hide_stock')->default(0);
            $table->boolean('is_bot')->default(false);
            $table->unsignedFloat('bot_physical_endurance')->default(1);
            $table->enum('status', ['DISCONNECTED', 'BORDER_CROSSED', 'BLOCKED'])->nullable();
            $table->enum('game_result', ['PLAYING', 'CAUGHT'])->default('PLAYING');
            $table->timestamp('catching_finished_at')->nullable();
            $table->timestamp('mission_finished_at')->nullable();
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

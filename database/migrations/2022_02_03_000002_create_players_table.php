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
            $table->json('config')->nullable();
            $table->point('global_position')->nullable(); // Ostatnia ujawniona globalnie pozycja gracza (wszyscy gracze poza agentem, a złodziej tylko podczas ujawniania) | Usuwane po zakończeniu gry
            $table->point('hidden_position')->nullable(); // Ostatnia pozycja gracza ujawniana tylko swojej frakcji (agent oraz złodziej) | Usuwane po zakończeniu gry
            $table->point('fake_position')->nullable(); // Fake'owa pozycja złodzieja | Usuwane po wygaśnięciu lub zakończeniu gry
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_catching')->default(false); // Flaga określająca czy gracz łapie złodzieja
            $table->boolean('is_caughting')->default(false); // Flaga określająca czy złodziej jest łapany
            $table->boolean('voting_answer')->nullable();
            $table->enum('status', Validation::getPlayerStatuses())->default('CONNECTED');
            $table->enum('failed_voting_type', Validation::getVotingTypes())->nullable(); // Flaga informująca każdego użytkownika jednokrotnie, że głosowanie się nie powiodło
            $table->unsignedTinyInteger('warning_number')->default(0);
            $table->unsignedBigInteger('ping')->default(0); // wyrażone w [ms]
            $table->unsignedBigInteger('average_ping')->default(0); // wyrażone w [ms]
            $table->unsignedBigInteger('samples_number')->default(0);
            $table->timestamp('expected_time_at')->nullable();
            $table->timestamp('black_ticket_finished_at')->nullable();
            $table->timestamp('fake_position_finished_at')->nullable();
            $table->timestamp('caught_at')->nullable();
            $table->timestamp('disconnecting_finished_at')->nullable();
            $table->timestamp('crossing_boundary_finished_at')->nullable();
            $table->timestamp('speed_exceeded_at')->nullable(); // Data przekroczenia prędkości przez jakiegoś gracza i trzymana przez SPEED_EXCEEDED_TIMEOUT sekund
            $table->timestamp('next_voting_starts_at')->nullable();
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

// Struktura JSONa z domyślnymi wartościami dla pola "config"
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

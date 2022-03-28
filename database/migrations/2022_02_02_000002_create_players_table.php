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
            $table->lineString('track')->nullable();
            $table->lineString('disclosure_track')->nullable();
            $table->point('mission_performed')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->float('direction')->default(0);
            $table->unsignedTinyInteger('hide_stock')->default(0); // określa ile odkryć w przód złodziej ma ochronę przed ujawnieniem pozycji
            $table->boolean('is_fake_position_active')->default(false); // określa czy najbliższa ujawniona pozycja złodzieja ma być fake'owa (już zaktualizowana w disclosure_track)
            $table->boolean('is_bot')->default(false);
            $table->enum('status', Validation::getPlayerStatuses())->nullable();
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

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
            $table->integerIncrements('id');
            $table->char('code', 8)->unique(); // Kodowane natywnie
            $table->enum('game_mode', ['STANDARD', 'CHECKPOINT'])->default('STANDARD');
            $table->enum('disclosure_mode', ['TIME_PERIOD', 'MONITORING'])->default('TIME_PERIOD');
            $table->boolean('killing_mode')->default(false);
            $table->unsignedMediumInteger('game_duration')->default(1800); // czas w sekundach
            $table->unsignedMediumInteger('disclosure_period')->default(300); // czas w sekundach
            $table->unsignedMediumInteger('killing_time')->default(90); // czas w sekundach
            $table->unsignedTinyInteger('checkpoints_number')->default(5);
            $table->unsignedTinyInteger('monitorings_number')->default(5);
            $table->unsignedTinyInteger('checkpoint_radius')->default(25);
            $table->unsignedTinyInteger('monitoring_radius')->default(25);
            $table->unsignedTinyInteger('killing_radius')->default(25);
            $table->unsignedTinyInteger('catch_radius')->default(25);
            $table->unsignedTinyInteger('policemen_number')->default(5);
            $table->unsignedTinyInteger('thieves_number')->default(1);
            $table->multiPoint('checkpoints')->nullable();
            $table->multiPoint('monitoring')->nullable();
            $table->polygon('boundary')->nullable();
            $table->dateTime('end_game')->nullable();
            $table->enum('status', ['WAITING_IN_ROOM', 'GAME_IN_PROGRESS'])->default('WAITING_IN_ROOM');
            $table->unsignedSmallInteger('game_counter')->default(0);
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

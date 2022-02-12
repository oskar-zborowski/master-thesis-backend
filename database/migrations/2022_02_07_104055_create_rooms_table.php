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
            $table->id();
            $table->foreignId('host_id')->references('id')->on('devices')->nullable()->nullOnDelete();
            $table->char('code', 8); // Kodowane automatycznie
            $table->unsignedTinyInteger('game_counter');
            $table->unsignedTinyInteger('policemen_number')->default(5);
            $table->unsignedTinyInteger('thieves_number')->default(1);
            $table->enum('game_mode', ['Scotland Yard', 'Mission: Impossible'])->default('Scotland Yard');
            $table->unsignedMediumInteger('game_duration')->default(1800); // czas w sekundach
            $table->unsignedSmallInteger('disclosures_interval')->default(180); // czas w sekundach
            $table->unsignedSmallInteger('catchers_number')->default(2);
            $table->unsignedSmallInteger('catch_radius')->default(50); // dystans w metrach
            $table->unsignedSmallInteger('catch_time')->default(3); // czas w sekundach
            $table->unsignedTinyInteger('missions_number')->default(5);
            $table->unsignedSmallInteger('mission_radius')->default(50); // dystans w metrach
            $table->unsignedSmallInteger('mission_time')->default(10); // czas w sekundach
            $table->unsignedTinyInteger('monitorings_number')->default(0);
            $table->unsignedSmallInteger('monitoring_radius')->default(50); // dystans w metrach
            $table->polygon('boundary')->nullable();
            $table->multiPoint('mission_centers')->nullable();
            $table->multiPoint('monitoring_centers')->nullable();
            $table->unsignedSmallInteger('escape_time')->default(300); // czas w sekundach
            $table->boolean('is_direction_visible')->default(true);
            $table->unsignedTinyInteger('black_tickets_number')->default(0);
            $table->unsignedTinyInteger('agents_number')->default(0);
            $table->dateTime('end_game')->nullable();
            $table->enum('status', ['WAITING IN ROOM', 'GAME IN PROGRESS', 'GAME OVER'])->default('WAITING IN ROOM');
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

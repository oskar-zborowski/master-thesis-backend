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
            $table->enum('game_mode', ['Scotland Yard', 'Mission: Impossible'])->default('Scotland Yard');
            $table->unsignedMediumInteger('game_duration')->default(1800); // czas w sekundach
            $table->dateTime('end_game')->nullable();
            $table->unsignedSmallInteger('escape_time')->default(300); // czas w sekundach
            $table->unsignedSmallInteger('disclosures_interval')->default(180); // czas w sekundach
            $table->unsignedTinyInteger('policemen_number')->default(5);
            $table->unsignedTinyInteger('agents_number')->default(0);
            $table->unsignedTinyInteger('saboteurs_number')->default(0);
            $table->float('saboteur_probability')->default(0);
            $table->unsignedTinyInteger('thieves_number')->default(1);
            $table->unsignedSmallInteger('catchers_number')->default(2);
            $table->unsignedSmallInteger('catch_radius')->default(50); // dystans w metrach
            $table->unsignedSmallInteger('catch_time')->default(5); // czas w sekundach
            $table->unsignedTinyInteger('missions_number')->default(5);
            $table->multiPoint('mission_centers')->nullable();
            $table->multiPoint('missions_completed')->nullable();
            $table->unsignedSmallInteger('mission_radius')->default(50); // dystans w metrach
            $table->unsignedSmallInteger('mission_time')->default(10); // czas w sekundach
            $table->unsignedTinyInteger('monitorings_number')->default(0);
            $table->multiPoint('monitoring_centers')->nullable();
            $table->unsignedSmallInteger('monitoring_radius')->default(50); // dystans w metrach
            $table->unsignedTinyInteger('monitoring_centrals_number')->default(0);
            $table->multiPoint('monitoring_central')->nullable();
            $table->polygon('boundary')->nullable();
            $table->boolean('with_monitoring_central')->default(false);
            $table->boolean('is_role_random')->default(true);
            $table->boolean('is_direction_visible')->default(true);
            $table->boolean('is_thief_disclosure_visible')->default(true);
            $table->boolean('is_catching_visible')->default(true);
            $table->unsignedTinyInteger('black_tickets_number')->default(0);
            $table->unsignedTinyInteger('black_tickets_used_number')->default(0);
            $table->unsignedTinyInteger('white_tickets_number')->default(0);
            $table->unsignedTinyInteger('white_tickets_used_number')->default(0);
            $table->unsignedTinyInteger('fake_positions_number')->default(0);
            $table->unsignedTinyInteger('fake_positions_used_number')->default(0);
            $table->unsignedSmallInteger('fake_position_radius')->default(250); // dystans w metrach
            $table->enum('status', ['WAITING IN ROOM', 'GAME IN PROGRESS', 'GAME PAUSED', 'GAME OVER'])->default('WAITING IN ROOM');
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

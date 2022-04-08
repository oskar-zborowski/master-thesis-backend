<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\SpatialBuilder;

class Player extends BaseModel
{
    protected $hidden = [
        'id',
        'room_id',
        'user_id',
        'avatar',
        'role',
        'player_config',
        'track',
        'disclosures',
        'missions_completed',
        'disclosed_position',
        'thief_fake_position',
        'direction',
        'mission_performed',
        'hide_stock',
        'protected_disclosure',
        'is_bot',
        'status',
        'warning_number',
        'average_ping',
        'standard_deviation',
        'samples_number',
        'expected_time_at',
        'crossing_border_finished_at',
        'mission_finished_at',
        'catching_finished_at',
        'caught_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'player_config' => 'array',
        'track' => 'array',
        'disclosures' => 'array',
        'missions_completed' => 'array',
        'disclosed_position' => Point::class,
        'thief_fake_position' => Point::class,
        'direction' => 'float',
        'mission_performed' => 'integer',
        'hide_stock' => 'integer',
        'protected_disclosure' => 'boolean',
        'is_bot' => 'boolean',
        'warning_number' => 'integer',
        'average_ping' => 'integer',
        'standard_deviation' => 'integer',
        'samples_number' => 'integer',
        'expected_time_at' => 'string',
        'crossing_border_finished_at' => 'string',
        'mission_finished_at' => 'string',
        'catching_finished_at' => 'string',
        'caught_at' => 'boolean',
        'updated_at' => 'string',
    ];

    public function newEloquentBuilder($query): SpatialBuilder {
        return new SpatialBuilder($query);
    }

    public function room() {
        return $this->belongsTo(Room::class);
    }
}

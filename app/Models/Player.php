<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\MultiPoint;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\SpatialBuilder;

class Player extends BaseModel
{
    protected $hidden = [
        'room_id',
        'user_id',
        'role',
        'player_config',
        'track',
        'disclosure',
        'thief_fake_position',
        'direction',
        'hide_stock',
        'protected_disclosure',
        'average_ping',
        'standard_deviation',
        'samples_number',
        'expected_time_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'player_config' => 'array',
        'track' => 'array',
        'disclosure' => 'array',
        'disclosed_position' => Point::class,
        'thief_fake_position' => Point::class,
        'mission_performed' => Point::class,
        'missions_completed' => MultiPoint::class,
        'direction' => 'float',
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
}

<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\MultiPoint;
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
        'thief_track',
        'track',
        'disclosed_thief_position',
        'thief_fake_position',
        'detected_thief_position',
        'mission_performed',
        'missions_completed',
        'direction',
        'hide_stock',
        'is_bot',
        'status',
        'warning_number',
        'average_ping',
        'standard_deviation',
        'samples_number',
        'expected_time_at',
        'mission_finished_at',
        'catching_finished_at',
        'caught_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'player_config' => 'array',
        'thief_track' => 'array',
        'track' => LineString::class,
        'disclosed_thief_position' => Point::class,
        'thief_fake_position' => Point::class,
        'detected_thief_position' => MultiPoint::class,
        'mission_performed' => Point::class,
        'missions_completed' => MultiPoint::class,
        'direction' => 'float',
        'hide_stock' => 'integer',
        'is_bot' => 'boolean',
        'warning_number' => 'integer',
        'average_ping' => 'integer',
        'standard_deviation' => 'integer',
        'samples_number' => 'integer',
        'expected_time_at' => 'string',
        'mission_finished_at' => 'string',
        'catching_finished_at' => 'string',
        'updated_at' => 'string',
    ];

    public function newEloquentBuilder($query): SpatialBuilder {
        return new SpatialBuilder($query);
    }
}

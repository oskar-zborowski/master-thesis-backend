<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\SpatialBuilder;

class Player extends BaseModel
{
    protected $hidden = [
        'room_id',
        'user_id',
        'config',
        'track',
        'hidden_position',
        'fake_position',
        'average_ping',
        'standard_deviation',
        'samples_number',
        'expected_time_at',
        'black_ticket_finished_at',
        'fake_position_finished_at',
        'next_voting_starts_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'config' => 'array',
        'track' => 'array',
        'global_position' => Point::class,
        'hidden_position' => Point::class,
        'fake_position' => Point::class,
        'is_bot' => 'boolean',
        'is_crossing_boundary' => 'boolean',
        'voting_answer' => 'boolean',
        'warning_number' => 'integer',
        'average_ping' => 'integer',
        'standard_deviation' => 'integer',
        'samples_number' => 'integer',
        'expected_time_at' => 'string',
        'black_ticket_finished_at' => 'string',
        'fake_position_finished_at' => 'string',
        'caught_at' => 'boolean',
        'disconnecting_finished_at' => 'string',
        'crossing_boundary_finished_at' => 'string',
        'next_voting_starts_at' => 'string',
        'updated_at' => 'string',
    ];

    protected $with = [
        'user',
    ];

    public function newEloquentBuilder($query): SpatialBuilder {
        return new SpatialBuilder($query);
    }

    public function room() {
        return $this->belongsTo(Room::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}

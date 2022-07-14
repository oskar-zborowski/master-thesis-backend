<?php

namespace App\Models;

class Player extends BaseModel
{
    protected $hidden = [
        'room_id',
        'user_id',
        'role',
        'config',
        'track',
        'global_position',
        'hidden_position',
        'fake_position',
        'failed_voting_type',
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
        'is_bot' => 'boolean',
        'is_caughting' => 'boolean',
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
        'speed_exceeded_at' => 'string',
        'next_voting_starts_at' => 'string',
        'updated_at' => 'string',
    ];

    public function room() {
        return $this->belongsTo(Room::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}

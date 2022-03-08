<?php

namespace App\Models;

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
        'disclosure_track',
        'missions_completed',
        'direction',
        'hide_stock',
        'is_bot',
        'bot_physical_endurance',
        'status',
        'average_ping',
        'standard_deviation',
        'samples_number',
        'expected_time',
        'catching_finished_at',
        'caught_at',
        'mission_finished_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'player_config' => 'array',
        'direction' => 'float',
        'hide_stock' => 'integer',
        'is_bot' => 'boolean',
        'bot_physical_endurance' => 'float',
        'average_ping' => 'integer',
        'standard_deviation' => 'integer',
        'samples_number' => 'integer',
        'expected_time' => 'integer',
        'catching_finished_at' => 'string',
        'mission_finished_at' => 'string',
        'updated_at' => 'string',
    ];
}

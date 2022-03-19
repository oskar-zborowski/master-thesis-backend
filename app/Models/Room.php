<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class Room extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'host_id',
        'street',
        'city',
        'voivodeship',
        'country',
        'game_config',
        'boundary',
        'mission_centers',
        'monitoring_centers',
        'monitoring_centrals',
        'game_paused_at',
        'game_ended_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'game_config' => 'array',
        'game_started_at' => 'string',
        'game_paused_at' => 'string',
        'game_ended_at' => 'string',
        'next_disclosure_at' => 'string',
    ];

    protected $encryptable = [
        'code' => 6,
    ];
}

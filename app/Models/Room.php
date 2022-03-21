<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use MatanYadaev\EloquentSpatial\SpatialBuilder;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

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
        'boundary' => Polygon::class,
        'mission_centers' => Point::class,
        'monitoring_centers' => Point::class,
        'monitoring_centrals' => Point::class,
        'game_started_at' => 'string',
        'game_paused_at' => 'string',
        'game_ended_at' => 'string',
        'next_disclosure_at' => 'string',
    ];

    protected $encryptable = [
        'code' => 6,
    ];

    public function players() {
        return $this->hasMany(Player::class);
    }

    public function newEloquentBuilder($query): SpatialBuilder {
        return new SpatialBuilder($query);
    }
}

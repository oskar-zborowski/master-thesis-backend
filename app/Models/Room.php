<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\SpatialBuilder;

class Room extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'host_id',
        'gps_location',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'boundary_polygon',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'counter' => 'integer',
        'game_config' => 'array',
        'boundary_polygon' => Polygon::class,
        'game_started_at' => 'string',
        'game_ended_at' => 'string',
        'next_disclosure_at' => 'string',
    ];

    protected $with = [
        'host',
        'players',
    ];

    protected $encryptable = [
        'code' => 6,
        'gps_location' => 20,
        'house_number' => 10,
        'street' => 70,
        'housing_estate' => 70,
        'district' => 70,
        'city' => 40,
        'voivodeship' => 20,
        'country' => 30,
        'boundary_points' => 419,
    ];

    public function host() {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players() {
        return $this->hasMany(Player::class);
    }

    public function newEloquentBuilder($query): SpatialBuilder {
        return new SpatialBuilder($query);
    }

    public function getData() {
        return [
            'Room' => $this,
            // Przysyłać dodatkowo informację o UTC żeby aplikacja wiedziała o ile skorygować czas względem czasu serwera
        ];
    }
}

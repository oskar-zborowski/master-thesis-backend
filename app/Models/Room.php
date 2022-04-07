<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use MatanYadaev\EloquentSpatial\Objects\MultiPolygon;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\SpatialBuilder;

class Room extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'host_id',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'boundary',
        'missions',
        'monitoring_cameras',
        'monitoring_centrals',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'game_config' => 'array',
        'boundary' => Polygon::class,
        'missions' => MultiPolygon::class,
        'monitoring_cameras' => MultiPolygon::class,
        'monitoring_centrals' => MultiPolygon::class,
        'geometries_confirmed' => 'boolean',
        'game_started_at' => 'string',
        'game_ended_at' => 'string',
        'next_disclosure_at' => 'string',
    ];

    protected $with = [
        'host',
    ];

    protected $encryptable = [
        'code' => 6,
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
            'Room' => 'test'
        ];
    }
}

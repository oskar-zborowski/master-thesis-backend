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
        'gps_location',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'boundary_points',
        'boundary_polygon',
        'mission_points',
        'mission_polygons',
        'monitoring_camera_points',
        'monitoring_camera_polygons',
        'monitoring_central_points',
        'monitoring_central_polygons',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'game_config' => 'array',
        'boundary_polygon' => Polygon::class,
        'mission_polygons' => MultiPolygon::class,
        'monitoring_camera_polygons' => MultiPolygon::class,
        'monitoring_central_polygons' => MultiPolygon::class,
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
        'gps_location' => 22,
        'house_number' => 10,
        'street' => 70,
        'housing_estate' => 70,
        'district' => 70,
        'city' => 40,
        'voivodeship' => 20,
        'country' => 30,
        'boundary_points' => 14949,
        'mission_points' => 1149,
        'monitoring_camera_points' => 229,
        'monitoring_central_points' => 114,
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

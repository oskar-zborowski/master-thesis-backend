<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\Point;

class GpsLog extends BaseModel
{
    protected $hidden = [
        'id',
        'user_id',
        'gps_location',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'gps_location' => Point::class,
    ];
}

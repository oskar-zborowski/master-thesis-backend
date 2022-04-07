<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class GpsLog extends BaseModel
{
    protected $hidden = [
        'id',
        'user_id',
        'gps_location',
        'street',
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

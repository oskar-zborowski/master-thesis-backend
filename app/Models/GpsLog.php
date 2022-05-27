<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class GpsLog extends BaseModel
{
    use Encryptable;

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
        'id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'string',
    ];

    protected $encryptable = [
        'gps_location' => 22,
        'house_number' => 10,
        'street' => 70,
        'housing_estate' => 70,
        'district' => 70,
        'city' => 40,
        'voivodeship' => 20,
        'country' => 30,
    ];
}

<?php

namespace App\Models;

class Config extends BaseModel
{
    protected $hidden = [
        'id',
        'nominatim_is_busy',
        'nominatim_last_used_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'nominatim_is_busy' => 'boolean',
        'nominatim_last_used_at' => 'string',
    ];
}

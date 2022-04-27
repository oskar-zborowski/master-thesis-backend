<?php

namespace App\Models;

class Config extends BaseModel
{
    protected $hidden = [
        'id',
        'nominatim_is_busy',
        'ip_api_is_busy',
        'mail_is_busy',
        'nominatim_last_used_at',
        'ip_api_last_used_at',
        'mail_last_used_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'nominatim_is_busy' => 'boolean',
        'ip_api_is_busy' => 'boolean',
        'mail_is_busy' => 'boolean',
        'nominatim_last_used_at' => 'string',
        'ip_api_last_used_at' => 'string',
        'mail_last_used_at' => 'string',
    ];
}

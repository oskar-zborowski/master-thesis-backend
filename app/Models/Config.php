<?php

namespace App\Models;

class Config extends BaseModel
{
    protected $hidden = [
        'id',
        'log_counter',
        'utc_time',
        'is_nominatim_busy',
        'is_ip_api_busy',
        'is_mail_busy',
        'nominatim_last_used_at',
        'ip_api_last_used_at',
        'mail_last_used_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'log_counter' => 'integer',
        'is_nominatim_busy' => 'boolean',
        'is_ip_api_busy' => 'boolean',
        'is_mail_busy' => 'boolean',
        'nominatim_last_used_at' => 'string',
        'ip_api_last_used_at' => 'string',
        'mail_last_used_at' => 'string',
    ];
}

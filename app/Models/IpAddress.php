<?php

namespace App\Models;

class IpAddress extends BaseModel
{
    protected $hidden = [
        'id',
        'user_id',
        'ip_address',
        'request_counter',
        'blocked_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'request_counter' => 'integer',
    ];

    protected $encryptable = [
        'ip_address' => 45,
    ];
}

<?php

namespace App\Models;

class PersonalAccessToken extends BaseModel
{
    protected $hidden = [
        'id',
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'refresh_token',
        'abilities',
        'expiry_alert_at',
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'string',
    ];

    protected $encryptable = [
        'refresh_token' => 31,
    ];
}

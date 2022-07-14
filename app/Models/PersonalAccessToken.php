<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class PersonalAccessToken extends BaseModel
{
    use Encryptable;

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
        'tokenable_id' => 'integer',
        'expiry_alert_at' => 'string',
        'created_at' => 'string',
    ];

    protected $encryptable = [
        'refresh_token' => 31,
    ];
}

<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class IpAddress extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'id',
        'ip_address',
        'provider',
        'city',
        'voivodeship',
        'country',
        'is_mobile',
        'blocked_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_mobile' => 'boolean',
        'blocked_at' => 'string',
        'created_at' => 'string',
    ];

    protected $encryptable = [
        'ip_address' => 45,
        'provider' => 90,
        'city' => 90,
        'voivodeship' => 90,
        'country' => 60,
    ];

    public function connections() {
        return $this->hasMany(Connection::class);
    }
}

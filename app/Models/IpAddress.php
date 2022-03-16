<?php

namespace App\Models;

class IpAddress extends BaseModel
{
    protected $hidden = [
        'id',
        'ip_address',
        'blocked_at',
        'created_at',
        'updated_at',
    ];

    protected $encryptable = [
        'ip_address' => 45,
    ];

    public function connections() {
        return $this->hasMany(Connection::class);
    }
}

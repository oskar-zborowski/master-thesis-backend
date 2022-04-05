<?php

namespace App\Models;

use App\Http\Traits\Encryptable;

class IpAddress extends BaseModel
{
    use Encryptable;

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

    protected $casts = [
        'blocked_at' => 'boolean',
    ];

    public function connections() {
        return $this->hasMany(Connection::class);
    }
}

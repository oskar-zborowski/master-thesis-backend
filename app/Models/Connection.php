<?php

namespace App\Models;

class Connection extends BaseModel
{
    protected $hidden = [
        'id',
        'user_id',
        'ip_address_id',
        'successful_request_counter',
        'failed_request_counter',
        'malicious_request_counter',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'successful_request_counter' => 'integer',
        'failed_request_counter' => 'integer',
        'malicious_request_counter' => 'integer',
        'created_at' => 'string',
    ];

    public function ipAddress() {
        return $this->belongsTo(IpAddress::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}

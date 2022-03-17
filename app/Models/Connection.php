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
        'successful_request_counter' => 'integer',
        'failed_request_counter' => 'integer',
        'malicious_request_counter' => 'integer',
    ];

    protected $encryptable = [];

    protected $with = [
        'user',
        'ipAddress'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function ipAddress() {
        return $this->belongsTo(IpAddress::class);
    }
}

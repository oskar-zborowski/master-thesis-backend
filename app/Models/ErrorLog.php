<?php

namespace App\Models;

class ErrorLog extends BaseModel
{
    protected $hidden = [
        'id',
        'number',
        'connection_id',
        'type',
        'thrower',
        'file',
        'method',
        'line',
        'subject',
        'message',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'connection_id' => 'integer',
        'line' => 'integer',
    ];
}

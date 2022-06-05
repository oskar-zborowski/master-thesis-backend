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
        'message',
        'created_at',
        'updated_at',
    ];
}

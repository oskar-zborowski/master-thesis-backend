<?php

namespace App\Models;

class ErrorLog extends BaseModel
{
    protected $hidden = [
        'id',
        'connection_id',
        'type',
        'thrower',
        'description',
        'created_at',
        'updated_at',
    ];
}

<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Encryptable, HasFactory, Notifiable, HasApiTokens;

    protected $hidden = [
        'default_avatar',
        'producer',
        'model',
        'os_name',
        'os_version',
        'app_version',
        'uuid',
        'blocked_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'blocked_at' => 'string',
        'created_at' => 'string',
    ];

    protected $encryptable = [
        'name' => 15,
        'uuid' => 45,
    ];

    public function tokenable() {
        return $this->morphOne(PersonalAccessToken::class, 'tokenable');
    }

    public function connections() {
        return $this->hasMany(Connection::class);
    }

    public function gpsLogs() {
        return $this->hasMany(GpsLog::class);
    }

    public function players() {
        return $this->hasMany(Player::class);
    }

    public function getData() {
        return [
            'User' => $this
        ];
    }
}

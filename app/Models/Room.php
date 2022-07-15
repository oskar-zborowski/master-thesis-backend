<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use Illuminate\Support\Facades\Auth;

class Room extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'host_id',
        'reporting_user_id',
        'group_code',
        'gps_location',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'boundary_polygon',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'host_id' => 'integer',
        'reporting_user_id' => 'integer',
        'counter' => 'integer',
        'config' => 'array',
        'game_started_at' => 'string',
        'game_ended_at' => 'string',
        'next_disclosure_at' => 'string',
        'voting_ended_at' => 'string',
        'created_at' => 'string',
    ];

    protected $with = [
        'host',
        'reportingUser',
    ];

    protected $encryptable = [
        'group_code' => 11,
        'code' => 6,
        'gps_location' => 20,
        'house_number' => 10,
        'street' => 70,
        'housing_estate' => 70,
        'district' => 70,
        'city' => 40,
        'voivodeship' => 20,
        'country' => 30,
        'boundary_points' => 419,
    ];

    public function host() {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function reportingUser() {
        return $this->belongsTo(User::class, 'reporting_user_id');
    }

    public function players() {
        return $this->hasMany(Player::class);
    }

    public function getData() {

        /** @var User $user */
        $user = Auth::user();

        /** @var Player $currentPlayer */
        $currentPlayer = $this->players()->where('user_id', $user->id)->first();

        /** @var Player[] $players */
        $players = $this->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        $allPlayers = null;

        foreach ($players as $player) {
            $allPlayers[] = $player->getData($currentPlayer);
        }

        return [
            'Room' => $this,
            'Player' => $allPlayers,
            'UTC' => config('app.timezone'),
        ];
    }
}

<?php

namespace App\Models;

use App\Http\Traits\Encryptable;
use Illuminate\Support\Facades\Auth;

class Room extends BaseModel
{
    use Encryptable;

    protected $hidden = [
        'id',
        'host_id',
        'reporting_user_id',
        'group_code',
        'code',
        'counter',
        'gps_location',
        'house_number',
        'street',
        'housing_estate',
        'district',
        'city',
        'voivodeship',
        'country',
        'config',
        'boundary_polygon',
        'boundary_points',
        'status',
        'game_result',
        'voting_type',
        'game_started_at',
        'game_ended_at',
        'next_disclosure_at',
        'voting_ended_at',
        'created_at',
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

        /** @var User $host */
        $host = $this->host()->first();

        /** @var User $reportingUser */
        $reportingUser = $this->reportingUser()->first();

        /** @var Player[] $players */
        $players = $this->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        $allPlayers = null;

        foreach ($players as $player) {
            $allPlayers[] = $player->getData($currentPlayer);
        }

        return [
            'Room' => [
                'id' => $this->id,
                'Host' => $host,
                'ReportingUser' => $reportingUser,
                'code' => $this->code,
                'counter' => $this->counter,
                'config' => $this->config,
                'boundary_points' => $this->boundary_points,
                'status' => $this->status,
                'game_result' => $this->game_result,
                'voting_type' => $this->voting_type,
                'game_started_at' => $this->game_started_at,
                'game_ended_at' => $this->game_ended_at,
                'next_disclosure_at' => $this->next_disclosure_at,
                'voting_ended_at' => $this->voting_ended_at,
                'created_at' => $this->created_at,
            ],
            'Player' => $allPlayers,
        ];
    }
}

<?php

namespace App\Models;

use MatanYadaev\EloquentSpatial\Objects\Point;

class Player extends BaseModel
{
    protected $hidden = [
        'id',
        'room_id',
        'user_id',
        'avatar',
        'role',
        'config',
        'global_position',
        'hidden_position',
        'fake_position',
        'is_bot',
        'is_catching',
        'is_caughting',
        'is_crossing_boundary',
        'voting_answer',
        'status',
        'failed_voting_type',
        'warning_number',
        'ping',
        'average_ping',
        'samples_number',
        'expected_time_at',
        'black_ticket_finished_at',
        'fake_position_finished_at',
        'caught_at',
        'disconnecting_finished_at',
        'crossing_boundary_finished_at',
        'speed_exceeded_at',
        'next_voting_starts_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'room_id' => 'integer',
        'user_id' => 'integer',
        'config' => 'array',
        'is_bot' => 'boolean',
        'is_catching' => 'boolean',
        'is_caughting' => 'boolean',
        'is_crossing_boundary' => 'boolean',
        'voting_answer' => 'boolean',
        'warning_number' => 'integer',
        'ping' => 'integer',
        'average_ping' => 'integer',
        'samples_number' => 'integer',
        'expected_time_at' => 'string',
        'black_ticket_finished_at' => 'string',
        'fake_position_finished_at' => 'string',
        'caught_at' => 'string',
        'disconnecting_finished_at' => 'string',
        'crossing_boundary_finished_at' => 'string',
        'speed_exceeded_at' => 'string',
        'next_voting_starts_at' => 'string',
        'created_at' => 'string',
        'updated_at' => 'string',
    ];

    public function room() {
        return $this->belongsTo(Room::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function getData(Player $player, string $utcTime) {

        $this->mergeCasts([
            'global_position' => Point::class,
            'hidden_position' => Point::class,
        ]);

        $botNames = [
            'Bot Alan',
            'Bot Chris',
            'Bot Connor',
            'Bot Elliot',
            'Bot Frank',
            'Bot Fred',
            'Bot Gary',
            'Bot Graham',
            'Bot Ivan',
            'Bot Jerry',
            'Bot John',
            'Bot Kevin',
            'Bot Larry',
            'Bot Mark',
            'Bot Matt',
            'Bot Mike',
            'Bot Oliver',
            'Bot Paul',
            'Bot Pheonix',
            'Bot Rick',
            'Bot Rock',
            'Bot Ryan',
            'Bot Scott',
            'Bot Shark',
            'Bot Stone',
            'Bot Ted',
            'Bot Tom',
            'Bot Will',
            'Bot Wolf',
            'Bot Yogi',
        ];

        /** @var User $user */
        $user = $this->user()->first();

        if ($user) {

            $userId = $user->id;
            $userName = $user->name;

        } else {

            $userId = null;

            if ($this->avatar !== null) {

                $botAvatarNumber = explode('_', $this->avatar);
                $botAvatarNumber = (int) $botAvatarNumber[1];

                $userName = $botNames[$botAvatarNumber-1];

            } else {
                $userName = 'Bot ...';
            }
        }

        if ($player->role != 'THIEF' || $this->role == 'THIEF' || $player->status == 'SUPERVISING') {
            $role = $this->role;
        } else {
            $role = null;
        }

        if ($player->role != 'THIEF' && $this->role != 'THIEF' || $player->role == 'THIEF' && $this->role == 'THIEF' || $player->status == 'SUPERVISING') {

            $config = $this->config;
            $globalPosition = $this->hidden_position ? "{$this->hidden_position->longitude} {$this->hidden_position->latitude}" : null;

            if ($this->black_ticket_finished_at !== null) {
                $blackTicketFinishedAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->black_ticket_finished_at)));
            } else {
                $blackTicketFinishedAt = null;
            }

            if ($this->fake_position_finished_at !== null) {
                $fakePositionFinishedAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->fake_position_finished_at)));
            } else {
                $fakePositionFinishedAt = null;
            }

        } else {
            $config = null;
            $globalPosition = $this->global_position ? "{$this->global_position->longitude} {$this->global_position->latitude}" : null;
            $blackTicketFinishedAt = null;
            $fakePositionFinishedAt = null;
        }

        if ($player->user_id == $this->user_id) {

            $failedVotingType = $this->failed_voting_type;

            if ($failedVotingType) {
                $this->failed_voting_type = null;
                $this->save();
            }

            $expectedTimeAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->expected_time_at)));

            if ($this->next_voting_starts_at !== null) {
                $nextVotingStartsAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->next_voting_starts_at)));
            } else {
                $nextVotingStartsAt = null;
            }

        } else {
            $failedVotingType = null;
            $expectedTimeAt = null;
            $nextVotingStartsAt = null;
        }

        if ($this->caught_at !== null) {
            $caughtAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->caught_at)));
        } else {
            $caughtAt = null;
        }

        if ($this->disconnecting_finished_at !== null) {
            $disconnectingFinishedAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->disconnecting_finished_at)));
        } else {
            $disconnectingFinishedAt = null;
        }

        if ($this->crossing_boundary_finished_at !== null) {
            $crossingBoundaryFinishedAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->crossing_boundary_finished_at)));
        } else {
            $crossingBoundaryFinishedAt = null;
        }

        $createdAt = date('Y-m-d H:i:s', strtotime($utcTime, strtotime($this->created_at)));

        return [
            'id' => $this->id,
            'User' => [
                'id' => $userId,
                'name' => $userName,
            ],
            'avatar' => $this->avatar,
            'role' => $role,
            'config' => $config,
            'global_position' => $globalPosition,
            'is_bot' => $this->is_bot,
            'is_catching' => $this->is_catching,
            'is_caughting' => $this->is_caughting,
            'is_crossing_boundary' => $this->is_crossing_boundary,
            'voting_answer' => $this->voting_answer,
            'status' => $this->status,
            'failed_voting_type' => $failedVotingType,
            'warning_number' => $this->warning_number,
            'ping' => $this->ping,
            'expected_time_at' => $expectedTimeAt,
            'black_ticket_finished_at' => $blackTicketFinishedAt,
            'fake_position_finished_at' => $fakePositionFinishedAt,
            'caught_at' => $caughtAt,
            'disconnecting_finished_at' => $disconnectingFinishedAt,
            'crossing_boundary_finished_at' => $crossingBoundaryFinishedAt,
            'is_speed_exceeded' => $this->speed_exceeded_at ? true : false,
            'next_voting_starts_at' => $nextVotingStartsAt,
            'created_at' => $createdAt,
        ];
    }
}

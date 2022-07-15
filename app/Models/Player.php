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
        'global_position' => Point::class,
        'hidden_position' => Point::class,
        'fake_position' => Point::class,
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

    public function getData(Player $player) {

        if ($player->role != 'THIEF' || $this->role == 'THIEF') {
            $role = $this->role;
        } else {
            $role = null;
        }

        if ($player->role != 'THIEF' && $this->role != 'THIEF' || $player->role == 'THIEF' && $this->role == 'THIEF') {
            $config = $this->config;
            $hiddenPosition = $this->hidden_position ? "{$this->hidden_position->longitude} {$this->hidden_position->latitude}" : null;
            $blackTicketFinishedAt = $this->black_ticket_finished_at;
            $fakePositionFinishedAt = $this->fake_position_finished_at;
        } else {
            $config = null;
            $hiddenPosition = null;
            $blackTicketFinishedAt = null;
            $fakePositionFinishedAt = null;
        }

        if ($player->user_id == $this->user_id) {

            $failedVotingType = $this->failed_voting_type;

            if ($failedVotingType) {
                $this->failed_voting_type = null;
                $this->save();
            }

            $expectedTimeAt = $this->expected_time_at;
            $nextVotingStartsAt = $this->next_voting_starts_at;

        } else {
            $failedVotingType = null;
            $expectedTimeAt = null;
            $nextVotingStartsAt = null;
        }

        return [
            'id' => $this->id,
            'User' => [
                'id' => $this->user()->first()->id,
                'name' => $this->user()->first()->name,
            ],
            'avatar' => $this->avatar,
            'role' => $role,
            'config' => $config,
            'global_position' => $this->global_position ? "{$this->global_position->longitude} {$this->global_position->latitude}" : null,
            'hidden_position' => $hiddenPosition,
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
            'caught_at' => $this->caught_at,
            'disconnecting_finished_at' => $this->disconnecting_finished_at,
            'crossing_boundary_finished_at' => $this->crossing_boundary_finished_at,
            'is_speed_exceeded' => $this->speed_exceeded_at ? true : false,
            'next_voting_starts_at' => $nextVotingStartsAt,
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Libraries;

use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;

/**
 * Klasa przechowująca ustawienia potrzebne do inicjalizacji gry
 */
class JsonConfig
{
    public static function getDefaultGameConfig() {
        return [
            'actor' => [
                'policeman' => [
                    'number' => 5,
                    'catching' => [
                        'number' => 3,
                        'radius' => 100,
                    ],
                ],
                'thief' => [
                    'number' => 1,
                    'escape_duration' => 300,
                    'disclosure_interval' => 300,
                    'black_ticket' => [
                        'number' => 0,
                        'probability' => 0.5,
                        'duration' => 300,
                    ],
                    'fake_position' => [
                        'number' => 0,
                        'probability' => 0.5,
                        'duration' => 300,
                    ],
                ],
                'agent' => [
                    'number' => 0,
                ],
                'pegasus' => [
                    'number' => 0,
                    'probability' => 0.5,
                    'white_ticket' => [
                        'number' => 0,
                        'probability' => 0.5,
                    ],
                ],
                'fatty_man' => [
                    'number' => 0,
                    'probability' => 0.5,
                ],
                'eagle' => [
                    'number' => 0,
                    'probability' => 0.5,
                ],
            ],
            'duration' => [
                'scheduled' => 3600,
                'real' => 0,
            ],
            'other' => [
                'is_role_random' => true,
                'bot_speed' => 2.5,
                'max_speed' => 15,
                'warning_number' => 2,
                'is_pause_after_disconnecting' => true,
                'disconnecting_countdown' => 60,
                'crossing_boundary_countdown' => 60,
            ],
        ];
    }

    public static function setGameConfig(Room $room, UpdateRoomRequest $request) {

        $gameConfig = $room->game_config;

        if ($request->actor_policeman_number !== null) {
            $gameConfig['actor']['policeman']['number'] = $request->actor_policeman_number;
        }

        if ($request->actor_policeman_catching_number !== null) {
            $gameConfig['actor']['policeman']['catching']['number'] = $request->actor_policeman_catching_number;
        }

        if ($request->actor_policeman_catching_radius !== null) {
            $gameConfig['actor']['policeman']['catching']['radius'] = $request->actor_policeman_catching_radius;
        }

        if ($request->actor_thief_number !== null) {
            $gameConfig['actor']['thief']['number'] = $request->actor_thief_number;
        }

        if ($request->actor_thief_escape_duration !== null) {
            $gameConfig['actor']['thief']['escape_duration'] = $request->actor_thief_escape_duration;
        }

        if ($request->actor_thief_disclosure_interval !== null) {
            $gameConfig['actor']['thief']['disclosure_interval'] = $request->actor_thief_disclosure_interval;
        }

        if ($request->actor_thief_black_ticket_number !== null) {
            $gameConfig['actor']['thief']['black_ticket']['number'] = $request->actor_thief_black_ticket_number;
        }

        if ($request->actor_thief_black_ticket_probability !== null) {
            $gameConfig['actor']['thief']['black_ticket']['probability'] = $request->actor_thief_black_ticket_probability;
        }

        if ($request->actor_thief_black_ticket_duration !== null) {
            $gameConfig['actor']['thief']['black_ticket']['duration'] = $request->actor_thief_black_ticket_duration;
        }

        if ($request->actor_thief_fake_position_number !== null) {
            $gameConfig['actor']['thief']['fake_position']['number'] = $request->actor_thief_fake_position_number;
        }

        if ($request->actor_thief_fake_position_probability !== null) {
            $gameConfig['actor']['thief']['fake_position']['probability'] = $request->actor_thief_fake_position_probability;
        }

        if ($request->actor_thief_fake_position_duration !== null) {
            $gameConfig['actor']['thief']['fake_position']['duration'] = $request->actor_thief_fake_position_duration;
        }

        if ($request->actor_agent_number !== null) {
            $gameConfig['actor']['agent']['number'] = $request->actor_agent_number;
        }

        if ($request->actor_pegasus_number !== null) {
            $gameConfig['actor']['pegasus']['number'] = $request->actor_pegasus_number;
        }

        if ($request->actor_pegasus_probability !== null) {
            $gameConfig['actor']['pegasus']['probability'] = $request->actor_pegasus_probability;
        }

        if ($request->actor_pegasus_white_ticket_number !== null) {
            $gameConfig['actor']['pegasus']['white_ticket']['number'] = $request->actor_pegasus_white_ticket_number;
        }

        if ($request->actor_pegasus_white_ticket_probability !== null) {
            $gameConfig['actor']['pegasus']['white_ticket']['probability'] = $request->actor_pegasus_white_ticket_probability;
        }

        if ($request->actor_fatty_man_number !== null) {
            $gameConfig['actor']['fatty_man']['number'] = $request->actor_fatty_man_number;
        }

        if ($request->actor_fatty_man_probability !== null) {
            $gameConfig['actor']['fatty_man']['probability'] = $request->actor_fatty_man_probability;
        }

        if ($request->actor_eagle_number !== null) {
            $gameConfig['actor']['eagle']['number'] = $request->actor_eagle_number;
        }

        if ($request->actor_eagle_probability !== null) {
            $gameConfig['actor']['eagle']['probability'] = $request->actor_eagle_probability;
        }

        if ($request->duration_scheduled !== null) {
            $gameConfig['duration']['scheduled'] = $request->duration_scheduled;
        }

        if ($request->other_is_role_random !== null) {
            $gameConfig['other']['is_role_random'] = $request->other_is_role_random;
        }

        if ($request->other_bot_speed !== null) {
            $gameConfig['other']['bot_speed'] = $request->other_bot_speed;
        }

        if ($request->other_max_speed !== null) {
            $gameConfig['other']['max_speed'] = $request->other_max_speed;
        }

        if ($request->other_warning_number !== null) {
            $gameConfig['other']['warning_number'] = $request->other_warning_number;
        }

        if ($request->other_is_pause_after_disconnecting !== null) {
            $gameConfig['other']['is_pause_after_disconnecting'] = $request->other_is_pause_after_disconnecting;
        }

        if ($request->other_disconnecting_countdown !== null) {
            $gameConfig['other']['disconnecting_countdown'] = $request->other_disconnecting_countdown;
        }

        if ($request->other_crossing_boundary_countdown !== null) {
            $gameConfig['other']['crossing_boundary_countdown'] = $request->other_crossing_boundary_countdown;
        }

        return $gameConfig;
    }

    public static function getDefaultPlayerConfig() {
        return [
            'ticket' => [
                'black' => [
                    'number' => 0,
                    'used_number' => 0,
                ],
                'white' => [
                    'number' => 0,
                    'used_number' => 0,
                ],
                'fake_position' => [
                    'number' => 0,
                    'used_number' => 0,
                ],
            ],
        ];
    }
}

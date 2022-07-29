<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
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
                    'visibility_radius' => -1,
                    'are_circles_visible' => true,
                    'catching' => [
                        'number' => 3,
                        'radius' => 100,
                    ],
                ],
                'thief' => [
                    'number' => 1,
                    'probability' => 1,
                    'visibility_radius' => -1,
                    'are_circles_visible' => true,
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
                    'probability' => 0.5,
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

        $gameConfig = $room->config;

        $currentAgentNumber = 0;
        $currentPegasusNumber = 0;
        $currentFattyManNumber = 0;
        $currentEagleNumber = 0;
        $currentThiefNumber = 0;

        $totalAgentNumber = 0;
        $totalPegasusNumber = 0;
        $totalFattyManNumber = 0;
        $totalEagleNumber = 0;
        $totalThiefNumber = 0;

        $playersNumberFromCatchingFaction = 0;

        /** @var Player[] $players */
        $players = $room->players()->where('status', 'CONNECTED')->get();

        foreach ($players as $player) {
            if ($player->role == 'AGENT') {
                $currentAgentNumber++;
            } else if ($player->role == 'PEGASUS') {
                $currentPegasusNumber++;
            } else if ($player->role == 'FATTY_MAN') {
                $currentFattyManNumber++;
            } else if ($player->role == 'EAGLE') {
                $currentEagleNumber++;
            } else if ($player->role == 'THIEF') {
                $currentThiefNumber++;
            }
        }

        if ($request->actor_policeman_number !== null) {
            $policemenNumber = $request->actor_policeman_number;
        } else {
            $policemenNumber = $gameConfig['actor']['policeman']['number'];
        }

        if ($request->actor_policeman_catching_number !== null) {
            $catchersNumber = $request->actor_policeman_catching_number;
        } else {
            $catchersNumber = $gameConfig['actor']['policeman']['catching']['number'];
        }

        if ($request->actor_agent_number !== null) {
            $totalAgentNumber = $request->actor_agent_number;
            $playersNumberFromCatchingFaction += $request->actor_agent_number;
        } else {
            $totalAgentNumber = $gameConfig['actor']['agent']['number'];
            $playersNumberFromCatchingFaction += $gameConfig['actor']['agent']['number'];
        }

        if ($request->actor_pegasus_number !== null) {
            $totalPegasusNumber = $request->actor_pegasus_number;
            $playersNumberFromCatchingFaction += $request->actor_pegasus_number;
        } else {
            $totalPegasusNumber = $gameConfig['actor']['pegasus']['number'];
            $playersNumberFromCatchingFaction += $gameConfig['actor']['pegasus']['number'];
        }

        if ($request->actor_fatty_man_number !== null) {
            $totalFattyManNumber = $request->actor_fatty_man_number;
            $playersNumberFromCatchingFaction += $request->actor_fatty_man_number;
        } else {
            $totalFattyManNumber = $gameConfig['actor']['fatty_man']['number'];
            $playersNumberFromCatchingFaction += $gameConfig['actor']['fatty_man']['number'];
        }

        if ($request->actor_eagle_number !== null) {
            $totalEagleNumber = $request->actor_eagle_number;
            $playersNumberFromCatchingFaction += $request->actor_eagle_number;
        } else {
            $totalEagleNumber = $gameConfig['actor']['eagle']['number'];
            $playersNumberFromCatchingFaction += $gameConfig['actor']['eagle']['number'];
        }

        if ($request->actor_thief_number !== null) {
            $totalThiefNumber = $request->actor_thief_number;
        } else {
            $totalThiefNumber = $gameConfig['actor']['thief']['number'];
        }

        if ($totalAgentNumber < $currentAgentNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.agent-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($totalPegasusNumber < $currentPegasusNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.pegasus-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($totalFattyManNumber < $currentFattyManNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.fatty-man-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($totalEagleNumber < $currentEagleNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.eagle-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($totalThiefNumber < $currentThiefNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.thief-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($policemenNumber < $playersNumberFromCatchingFaction) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.policemen-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($policemenNumber < $catchersNumber) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.catchers-number-exceeded'),
                __FUNCTION__
            );
        }

        if ($request->actor_policeman_number !== null) {
            $gameConfig['actor']['policeman']['number'] = $request->actor_policeman_number;
        }

        if ($request->actor_policeman_visibility_radius !== null) {
            $gameConfig['actor']['policeman']['visibility_radius'] = $request->actor_policeman_visibility_radius;
        }

        if ($request->actor_policeman_are_circles_visible !== null) {
            $gameConfig['actor']['policeman']['are_circles_visible'] = $request->actor_policeman_are_circles_visible;
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

        if ($request->actor_thief_probability !== null) {
            $gameConfig['actor']['thief']['probability'] = $request->actor_thief_probability;
        }

        if ($request->actor_thief_visibility_radius !== null) {
            $gameConfig['actor']['thief']['visibility_radius'] = $request->actor_thief_visibility_radius;
        }

        if ($request->actor_thief_are_circles_visible !== null) {
            $gameConfig['actor']['thief']['are_circles_visible'] = $request->actor_thief_are_circles_visible;
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

        if ($request->actor_agent_probability !== null) {
            $gameConfig['actor']['agent']['probability'] = $request->actor_agent_probability;
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

    public static function getDefaultThiefConfig() {
        return [
            'black_ticket' => [
                'number' => 0,
                'used_number' => 0,
            ],
            'fake_position' => [
                'number' => 0,
                'used_number' => 0,
            ],
        ];
    }

    public static function getDefaultPegasusConfig() {
        return [
            'white_ticket' => [
                'number' => 0,
                'used_number' => 0,
            ],
        ];
    }
}

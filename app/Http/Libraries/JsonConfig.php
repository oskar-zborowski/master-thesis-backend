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
                ],
                'thief' => [
                    'number' => 1,
                ],
                'agent' => [
                    'number' => 0,
                ],
                'saboteur' => [
                    'number' => 0,
                    'probability' => 0.5,
                ],
            ],
            'game_duration' => [
                'scheduled' => 3600,
                'escape_time' => 600,
                'real' => 0,
            ],
            'catching' => [
                'number' => 3,
                'radius' => 100,
                'time' => 10,
            ],
            'disclosure' => [
                'interval' => 300,
                'after_starting' => false,
                'thief_direction' => false,
                'short_distance' => true,
                'thief_knows_when' => true,
                'policeman_sees_agent' => true,
                'saboteur_sees_thief' => false,
                'thief_knows_saboteur' => false,
                'after_crossing_border' => false,
            ],
            'mission' => [
                'number' => 5,
                'radius' => 50,
                'time' => 10,
                'all_visible' => true,
            ],
            'monitoring' => [
                'number' => 0,
                'radius' => 50,
                'central' => [
                    'number' => 0,
                    'radius' => 50,
                ],
            ],
            'ticket' => [
                'black' => [
                    'number' => 0,
                    'probability' => 0.5,
                ],
                'white' => [
                    'number' => 0,
                    'probability' => 0.5,
                ],
            ],
            'fake_position' => [
                'number' => 0,
                'probability' => 0.5,
            ],
            'game_pause' => [
                'after_disconnecting' => true,
                'after_crossing_border' => false,
            ],
            'other' => [
                'warning_number' => 2,
                'crossing_border_countdown' => 30,
                'max_speed' => 6,
                'bot_speed' => 2.5,
            ],
        ];
    }

    public static function setGameConfig(Room $room, UpdateRoomRequest $request) {

        $gameConfig = $room->game_config;

        if ($request->actor_policeman_number !== null) {
            $gameConfig['actor']['policeman']['number'] = $request->actor_policeman_number;
        }

        if ($request->actor_thief_number !== null) {
            $gameConfig['actor']['thief']['number'] = $request->actor_thief_number;
        }

        if ($request->actor_agent_number !== null) {
            $gameConfig['actor']['agent']['number'] = $request->actor_agent_number;
        }

        if ($request->actor_saboteur_number !== null) {
            $gameConfig['actor']['saboteur']['number'] = $request->actor_saboteur_number;
        }

        if ($request->actor_saboteur_probability !== null) {
            $gameConfig['actor']['saboteur']['probability'] = $request->actor_saboteur_probability;
        }

        if ($request->game_duration_scheduled !== null) {
            $gameConfig['game_duration']['scheduled'] = $request->game_duration_scheduled;
        }

        if ($request->game_duration_escape_time !== null) {
            $gameConfig['game_duration']['escape_time'] = $request->game_duration_escape_time;
        }

        if ($request->catching_number !== null) {
            $gameConfig['catching']['number'] = $request->catching_number;
        }

        if ($request->catching_radius !== null) {
            $gameConfig['catching']['radius'] = $request->catching_radius;
        }

        if ($request->catching_time !== null) {
            $gameConfig['catching']['time'] = $request->catching_time;
        }

        if ($request->disclosure_interval !== null) {
            $gameConfig['disclosure']['interval'] = $request->disclosure_interval;
        }

        if ($request->disclosure_after_starting !== null) {
            $gameConfig['disclosure']['after_starting'] = $request->disclosure_after_starting;
        }

        if ($request->disclosure_thief_direction !== null) {
            $gameConfig['disclosure']['thief_direction'] = $request->disclosure_thief_direction;
        }

        if ($request->disclosure_short_distance !== null) {
            $gameConfig['disclosure']['short_distance'] = $request->disclosure_short_distance;
        }

        if ($request->disclosure_thief_knows_when !== null) {
            $gameConfig['disclosure']['thief_knows_when'] = $request->disclosure_thief_knows_when;
        }

        if ($request->disclosure_policeman_sees_agent !== null) {
            $gameConfig['disclosure']['policeman_sees_agent'] = $request->disclosure_policeman_sees_agent;
        }

        if ($request->disclosure_saboteur_sees_thief !== null) {
            $gameConfig['disclosure']['saboteur_sees_thief'] = $request->disclosure_saboteur_sees_thief;
        }

        if ($request->disclosure_thief_knows_saboteur !== null) {
            $gameConfig['disclosure']['thief_knows_saboteur'] = $request->disclosure_thief_knows_saboteur;
        }

        if ($request->disclosure_after_crossing_border !== null) {
            $gameConfig['disclosure']['after_crossing_border'] = $request->disclosure_after_crossing_border;
        }

        if ($request->mission_number !== null) {
            $gameConfig['mission']['number'] = $request->mission_number;
        }

        if ($request->mission_radius !== null) {
            $gameConfig['mission']['radius'] = $request->mission_radius;
        }

        if ($request->mission_time !== null) {
            $gameConfig['mission']['time'] = $request->mission_time;
        }

        if ($request->mission_all_visible !== null) {
            $gameConfig['mission']['all_visible'] = $request->mission_all_visible;
        }

        if ($request->monitoring_number !== null) {
            $gameConfig['monitoring']['number'] = $request->monitoring_number;
        }

        if ($request->monitoring_radius !== null) {
            $gameConfig['monitoring']['radius'] = $request->monitoring_radius;
        }

        if ($request->monitoring_central_number !== null) {
            $gameConfig['monitoring']['central']['number'] = $request->monitoring_central_number;
        }

        if ($request->monitoring_central_radius !== null) {
            $gameConfig['monitoring']['central']['radius'] = $request->monitoring_central_radius;
        }

        if ($request->ticket_black_number !== null) {
            $gameConfig['ticket']['black']['number'] = $request->ticket_black_number;
        }

        if ($request->ticket_black_probability !== null) {
            $gameConfig['ticket']['black']['probability'] = $request->ticket_black_probability;
        }

        if ($request->ticket_white_number !== null) {
            $gameConfig['ticket']['white']['number'] = $request->ticket_white_number;
        }

        if ($request->ticket_white_probability !== null) {
            $gameConfig['ticket']['white']['probability'] = $request->ticket_white_probability;
        }

        if ($request->fake_position_number !== null) {
            $gameConfig['fake_position']['number'] = $request->fake_position_number;
        }

        if ($request->fake_position_probability !== null) {
            $gameConfig['fake_position']['probability'] = $request->fake_position_probability;
        }

        if ($request->game_pause_after_disconnecting !== null) {
            $gameConfig['game_pause']['after_disconnecting'] = $request->game_pause_after_disconnecting;
        }

        if ($request->game_pause_after_crossing_border !== null) {
            $gameConfig['game_pause']['after_crossing_border'] = $request->game_pause_after_crossing_border;
        }

        if ($request->other_warning_number !== null) {
            $gameConfig['other']['warning_number'] = $request->other_warning_number;
        }

        if ($request->other_crossing_border_countdown !== null) {
            $gameConfig['other']['crossing_border_countdown'] = $request->other_crossing_border_countdown;
        }

        if ($request->other_max_speed !== null) {
            $gameConfig['other']['max_speed'] = $request->other_max_speed;
        }

        if ($request->other_bot_speed !== null) {
            $gameConfig['other']['bot_speed'] = $request->other_bot_speed;
        }

        return $gameConfig;
    }
}

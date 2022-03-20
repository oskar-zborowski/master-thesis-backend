<?php

namespace App\Http\Libraries;

use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;

/**
 * Klasa przechowująca ustawienia potrzebne do inicjalizacji gry
 */
class JsonConfig
{
    public static function defaultGameConfig() {
        return [
            "actor" => [
                "policeman" => [
                    "number" => 5,
                ],
                "thief" => [
                    "number" => 1,
                ],
                "agent" => [
                    "number" => 0,
                ],
                "saboteur" => [
                    "number" => 1,
                    "probability" => 0.5,
                ],
            ],
            "bot" => [
                "policeman" => [
                    "maximum_speed" => 4,
                    "physical_endurance" => 0.8,
                    "level" => 2,
                ],
                "thief" => [
                    "maximum_speed" => 4,
                    "physical_endurance" => 0.8,
                    "level" => 2,
                ],
                "agent" => [
                    "maximum_speed" => 4,
                    "physical_endurance" => 0.8,
                    "level" => 2,
                ],
                "saboteur" => [
                    "maximum_speed" => 4,
                    "physical_endurance" => 0.8,
                    "level" => 2,
                ],
            ],
            "game_duration" => [
                "scheduled" => 1800,
                "real" => 0,
            ],
            "escape" => [
                "time" => 300,
            ],
            "catching" => [
                "number" => 2,
                "radius" => 50,
                "time" => 5,
            ],
            "disclosure" => [
                "interval" => 180,
                "after_starting" => false,
                "thief_direction" => true,
                "short_distance" => true,
                "thief_knows_when" => true,
                "agent" => true,
                "agent_knows_when" => true,
                "after_crossing_border" => false,
            ],
            "monitoring" => [
                "number" => 0,
                "radius" => 50,
                "random" => false,
                "central" => [
                    "number" => 0,
                    "radius" => 50,
                    "random" => false,
                ],
            ],
            "mission" => [
                "number" => 5,
                "radius" => 50,
                "time" => 10,
                "all_visible" => true,
            ],
            "ticket" => [
                "black" => [
                    "number" => 0,
                    "probability" => 0.5,
                ],
                "white" => [
                    "number" => 0,
                    "probability" => 0.5,
                ],
                "gold" => [
                    "number" => 0,
                    "probability" => 0.5,
                ],
                "silver" => [
                    "number" => 0,
                    "probability" => 0.5,
                ],
            ],
            "fake_position" => [
                "number" => 0,
                "probability" => 0.5,
                "radius" => 250,
                "random" => false,
            ],
            "game_pause" => [
                "after_disconnecting" => true,
                "after_crossing_border" => true,
            ],
            "other" => [
                "role_random" => true,
                "thief_knows_saboteur" => true,
                "saboteur_sees_thief" => true,
            ],
        ];
    }

    public static function gameConfig(Room $room, UpdateRoomRequest $request) {

        $gameConfig = $room->game_config;

        if ($request->policeman_number !== null) {
            $gameConfig['actor']['policeman']['number'] = $request->policeman_number;
        }

        if ($request->thief_number !== null) {
            $gameConfig['actor']['thief']['number'] = $request->thief_number;
        }

        if ($request->agent_number !== null) {
            $gameConfig['actor']['agent']['number'] = $request->agent_number;
        }

        if ($request->saboteur_number !== null) {
            $gameConfig['actor']['saboteur']['number'] = $request->saboteur_number;
        }

        if ($request->saboteur_probability !== null) {
            $gameConfig['actor']['saboteur']['probability'] = $request->saboteur_probability;
        }

        if ($request->bot_policeman_maximum_speed !== null) {
            $gameConfig['bot']['policeman']['maximum_speed'] = $request->bot_policeman_maximum_speed;
        }

        if ($request->bot_policeman_physical_endurance !== null) {
            $gameConfig['bot']['policeman']['physical_endurance'] = $request->bot_policeman_physical_endurance;
        }

        if ($request->bot_policeman_level !== null) {
            $gameConfig['bot']['policeman']['level'] = $request->bot_policeman_level;
        }

        if ($request->bot_thief_maximum_speed !== null) {
            $gameConfig['bot']['thief']['maximum_speed'] = $request->bot_thief_maximum_speed;
        }

        if ($request->bot_thief_physical_endurance !== null) {
            $gameConfig['bot']['thief']['physical_endurance'] = $request->bot_thief_physical_endurance;
        }

        if ($request->bot_thief_level !== null) {
            $gameConfig['bot']['thief']['level'] = $request->bot_thief_level;
        }

        if ($request->bot_agent_maximum_speed !== null) {
            $gameConfig['bot']['agent']['maximum_speed'] = $request->bot_agent_maximum_speed;
        }

        if ($request->bot_agent_physical_endurance !== null) {
            $gameConfig['bot']['agent']['physical_endurance'] = $request->bot_agent_physical_endurance;
        }

        if ($request->bot_agent_level !== null) {
            $gameConfig['bot']['agent']['level'] = $request->bot_agent_level;
        }

        if ($request->bot_saboteur_maximum_speed !== null) {
            $gameConfig['bot']['saboteur']['maximum_speed'] = $request->bot_saboteur_maximum_speed;
        }

        if ($request->bot_saboteur_physical_endurance !== null) {
            $gameConfig['bot']['saboteur']['physical_endurance'] = $request->bot_saboteur_physical_endurance;
        }

        if ($request->bot_saboteur_level !== null) {
            $gameConfig['bot']['saboteur']['level'] = $request->bot_saboteur_level;
        }

        if ($request->game_duration_scheduled !== null) {
            $gameConfig['game_duration']['scheduled'] = $request->game_duration_scheduled;
        }

        if ($request->escape_time !== null) {
            $gameConfig['escape']['time'] = $request->escape_time;
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

        if ($request->disclosure_agent !== null) {
            $gameConfig['disclosure']['agent'] = $request->disclosure_agent;
        }

        if ($request->disclosure_agent_knows_when !== null) {
            $gameConfig['disclosure']['agent_knows_when'] = $request->disclosure_agent_knows_when;
        }

        if ($request->disclosure_after_crossing_border !== null) {
            $gameConfig['disclosure']['after_crossing_border'] = $request->disclosure_after_crossing_border;
        }

        if ($request->monitoring_number !== null) {
            $gameConfig['monitoring']['number'] = $request->monitoring_number;
        }

        if ($request->monitoring_radius !== null) {
            $gameConfig['monitoring']['radius'] = $request->monitoring_radius;
        }

        if ($request->monitoring_random !== null) {
            $gameConfig['monitoring']['random'] = $request->monitoring_random;
        }

        if ($request->monitoring_central_number !== null) {
            $gameConfig['monitoring']['central']['number'] = $request->monitoring_central_number;
        }

        if ($request->monitoring_central_radius !== null) {
            $gameConfig['monitoring']['central']['radius'] = $request->monitoring_central_radius;
        }

        if ($request->monitoring_central_random !== null) {
            $gameConfig['monitoring']['central']['random'] = $request->monitoring_central_random;
        }

        if ($request->mission_number !== null) {
            $gameConfig['mission']['number'] = $request->mission_number;
        }

        if ($request->mission_radius !== null) {
            $gameConfig['mission']['radius'] = $request->mission_radius;
        }

        if ($request->mission_all_visible !== null) {
            $gameConfig['mission']['all_visible'] = $request->mission_all_visible;
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

        if ($request->ticket_gold_number !== null) {
            $gameConfig['ticket']['gold']['number'] = $request->ticket_gold_number;
        }

        if ($request->ticket_gold_probability !== null) {
            $gameConfig['ticket']['gold']['probability'] = $request->ticket_gold_probability;
        }

        if ($request->ticket_silver_number !== null) {
            $gameConfig['ticket']['silver']['number'] = $request->ticket_silver_number;
        }

        if ($request->ticket_silver_probability !== null) {
            $gameConfig['ticket']['silver']['probability'] = $request->ticket_silver_probability;
        }

        if ($request->fake_position_number !== null) {
            $gameConfig['fake_position']['number'] = $request->fake_position_number;
        }

        if ($request->fake_position_probability !== null) {
            $gameConfig['fake_position']['probability'] = $request->fake_position_probability;
        }

        if ($request->fake_position_radius !== null) {
            $gameConfig['fake_position']['radius'] = $request->fake_position_radius;
        }

        if ($request->fake_position_random !== null) {
            $gameConfig['fake_position']['random'] = $request->fake_position_random;
        }

        if ($request->game_pause_after_disconnecting !== null) {
            $gameConfig['game_pause']['after_disconnecting'] = $request->game_pause_after_disconnecting;
        }

        if ($request->game_pause_after_crossing_border !== null) {
            $gameConfig['game_pause']['after_crossing_border'] = $request->game_pause_after_crossing_border;
        }

        if ($request->other_role_random !== null) {
            $gameConfig['other']['role_random'] = $request->other_role_random;
        }

        if ($request->other_thief_knows_saboteur !== null) {
            $gameConfig['other']['thief_knows_saboteur'] = $request->other_thief_knows_saboteur;
        }

        if ($request->other_saboteur_sees_thief !== null) {
            $gameConfig['other']['saboteur_sees_thief'] = $request->other_saboteur_sees_thief;
        }

        return $gameConfig;
    }
}

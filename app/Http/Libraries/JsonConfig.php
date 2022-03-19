<?php

namespace App\Http\Libraries;

/**
 * Klasa przechowująca domyślne ustawienia potrzebne do inicjalizacji gry
 */
class JsonConfig
{
    public static function gameConfig() {
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
                "central" => [
                    "number" => 0,
                    "radius" => 50,
                ],
            ],
            "mission" => [
                "number" => 5,
                "radius" => 50,
                "time" => 10,
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
}

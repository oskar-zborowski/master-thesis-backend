<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'host_id' => 'nullable|integer|exists:users,id',
            'game_mode' => ['nullable', Rule::in(['SCOTLAND_YARD', 'MISSION_IMPOSSIBLE'])],
            'actor_policeman_number' => 'nullable|integer|between:1,25',
            'actor_thief_number' => 'nullable|integer|between:1,5',
            'actor_agent_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_probability' => 'nullable|numeric|between:0,1',
            'bot_policeman_maximum_speed' => 'nullable|numeric|between:1,10',
            'bot_policeman_physical_endurance' => 'nullable|numeric|between:0,1',
            'bot_policeman_level' => 'nullable|integer|between:1,3',
            'bot_thief_maximum_speed' => 'nullable|numeric|between:1,10',
            'bot_thief_physical_endurance' => 'nullable|numeric|between:0,1',
            'bot_thief_level' => 'nullable|integer|between:1,3',
            'bot_agent_maximum_speed' => 'nullable|numeric|between:1,10',
            'bot_agent_physical_endurance' => 'nullable|numeric|between:0,1',
            'bot_agent_level' => 'nullable|integer|between:1,3',
            'bot_saboteur_maximum_speed' => 'nullable|numeric|between:1,10',
            'bot_saboteur_physical_endurance' => 'nullable|numeric|between:0,1',
            'bot_saboteur_level' => 'nullable|integer|between:1,3',
            'game_duration_scheduled' => 'nullable|integer|between:0,10800',
            'escape_time' => 'nullable|integer|between:30,1800',
            'catching_number' => 'nullable|integer|between:1,3',
            'catching_radius' => 'nullable|integer|between:5,250',
            'catching_time' => 'nullable|integer|between:1,15',
            'disclosure_interval' => 'nullable|integer|between:0,900',
            'disclosure_after_starting' => 'nullable|boolean',
            'disclosure_thief_direction' => 'nullable|boolean',
            'disclosure_short_distance' => 'nullable|boolean',
            'disclosure_thief_knows_when' => 'nullable|boolean',
            'disclosure_agent' => 'nullable|boolean',
            'disclosure_agent_knows_when' => 'nullable|boolean',
            'disclosure_after_crossing_border' => 'nullable|boolean',
            'monitoring_number' => 'nullable|integer|between:0,10',
            'monitoring_radius' => 'nullable|integer|between:5,250',
            'monitoring_random' => 'nullable|boolean',
            'monitoring_central_number' => 'nullable|integer|between:0,5',
            'monitoring_central_radius' => 'nullable|integer|between:5,250',
            'monitoring_central_random' => 'nullable|boolean',
            'mission_number' => 'nullable|integer|between:3,50',
            'mission_radius' => 'nullable|integer|between:5,250',
            'mission_time' => 'nullable|integer|between:1,60',
            'mission_all_visible' => 'nullable|boolean',
            'ticket_black_number' => 'nullable|integer|between:0,5',
            'ticket_black_probability' => 'nullable|numeric|between:0,1',
            'ticket_white_number' => 'nullable|integer|between:0,5',
            'ticket_white_probability' => 'nullable|numeric|between:0,1',
            'ticket_gold_number' => 'nullable|integer|between:0,5',
            'ticket_gold_probability' => 'nullable|numeric|between:0,1',
            'ticket_silver_number' => 'nullable|integer|between:0,5',
            'ticket_silver_probability' => 'nullable|numeric|between:0,1',
            'fake_position_number' => 'nullable|numeric|between:0,5',
            'fake_position_probability' => 'nullable|numeric|between:0,1',
            'fake_position_radius' => 'nullable|integer|between:50,2500',
            'fake_position_random' => 'nullable|boolean',
            'game_pause_after_disconnecting' => 'nullable|boolean',
            'game_pause_after_crossing_border' => 'nullable|boolean',
            'other_role_random' => 'nullable|boolean',
            'other_thief_knows_saboteur' => 'nullable|boolean',
            'other_saboteur_sees_thief' => 'nullable|boolean',
            'boundary' => 'nullable|array|between:4,20',
            'mission_centers' => 'nullable|array|between:3,50',
            'monitoring_centers' => 'nullable|array|between:1,10',
            'monitoring_centrals' => 'nullable|array|between:1,5',
        ];
    }
}

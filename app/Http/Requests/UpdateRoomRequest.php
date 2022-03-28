<?php

namespace App\Http\Requests;

use App\Http\Libraries\Validation;
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
            'game_mode' => ['nullable', Rule::in(Validation::getGameModes())],
            'actor_policeman_number' => 'nullable|integer|between:1,25',
            'actor_thief_number' => 'nullable|integer|between:1,5',
            'actor_agent_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_probability' => 'nullable|numeric|between:0,1',
            'game_duration_scheduled' => 'nullable|integer|between:900,10800',
            'game_duration_escape_time' => 'nullable|integer|between:300,1800',
            'catching_number' => 'nullable|integer|between:1,5',
            'catching_radius' => 'nullable|integer|between:50,500',
            'catching_time' => 'nullable|integer|between:0,30',
            'disclosure_interval' => 'nullable|integer|between:0,1800',
            'disclosure_after_starting' => 'nullable|boolean',
            'disclosure_thief_direction' => 'nullable|boolean',
            'disclosure_short_distance' => 'nullable|boolean',
            'disclosure_thief_knows_when' => 'nullable|boolean',
            'disclosure_thief_knows_saboteur' => 'nullable|boolean',
            'disclosure_saboteur_sees_thief' => 'nullable|boolean',
            'disclosure_after_crossing_border' => 'nullable|boolean',
            'mission_number' => 'nullable|integer|between:3,50',
            'mission_radius' => 'nullable|integer|between:50,250',
            'mission_time' => 'nullable|integer|between:0,30',
            'mission_all_visible' => 'nullable|boolean',
            'monitoring_number' => 'nullable|integer|between:0,10',
            'monitoring_radius' => 'nullable|integer|between:50,250',
            'monitoring_central_number' => 'nullable|integer|between:0,5',
            'monitoring_central_radius' => 'nullable|integer|between:50,250',
            'ticket_black_number' => 'nullable|integer|between:0,5',
            'ticket_black_probability' => 'nullable|numeric|between:0,1',
            'ticket_white_number' => 'nullable|integer|between:0,3',
            'ticket_white_probability' => 'nullable|numeric|between:0,1',
            'fake_position_number' => 'nullable|integer|between:0,3',
            'fake_position_probability' => 'nullable|numeric|between:0,1',
            'game_pause_after_disconnecting' => 'nullable|boolean',
            'game_pause_after_crossing_border' => 'nullable|boolean',
            'other_role_random' => 'nullable|boolean',
            'other_warning_number' => 'nullable|integer|between:0,3',
            'other_max_speed' => 'nullable|numeric|between:0,10',
            'other_bot_speed' => 'nullable|numeric|between:2,10',
            'boundary' => 'nullable|array|between:1,11',
            'monitoring_cameras' => 'nullable|array|between:1,10',
            'monitoring_centrals' => 'nullable|array|between:1,5',
            'geometries_confirmed' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(['GAME_IN_PROGRESS', 'GAME_PAUSED'])],
        ];
    }
}

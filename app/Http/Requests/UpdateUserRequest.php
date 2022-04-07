<?php

namespace App\Http\Requests;

use App\Http\Libraries\Validation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'name' => 'nullable|string|between:1,15',
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['nullable', Rule::in(Validation::getAppVersions())],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }
}

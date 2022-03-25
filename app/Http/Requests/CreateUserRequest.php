<?php

namespace App\Http\Requests;

use App\Http\Libraries\Validation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'name' => 'required|string|between:1,15',
            'producer' => 'nullable|string|between:1,30',
            'model' => 'nullable|string|between:1,50',
            'os_name' => ['nullable', Rule::in(Validation::getOsNames())],
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(Validation::getAppVersions())],
            'uuid' => 'nullable|string|between:1,45',
        ];
    }
}

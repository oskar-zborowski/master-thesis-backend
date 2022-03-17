<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'name' => 'required|string|between:1,15',
            'producer' => 'nullable|string|between:1,30',
            'model' => 'nullable|string|between:1,50',
            'os_name' => ['nullable', Rule::in(['ANDROID', 'IOS'])],
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(['1.0.0'])],
            'uuid' => 'nullable|string|between:1,45',
        ];
    }
}

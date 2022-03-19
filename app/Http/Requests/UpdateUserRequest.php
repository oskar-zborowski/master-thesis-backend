<?php

namespace App\Http\Requests;

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
            'app_version' => ['nullable', Rule::in(['1.0.0'])],
        ];
    }
}

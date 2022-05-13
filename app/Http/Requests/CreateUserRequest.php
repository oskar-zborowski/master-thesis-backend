<?php

namespace App\Http\Requests;

use App\Http\Libraries\Validation;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return Validation::user_createUser();
    }
}

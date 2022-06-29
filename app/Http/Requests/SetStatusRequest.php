<?php

namespace App\Http\Requests;

use App\Http\Libraries\Validation;
use Illuminate\Foundation\Http\FormRequest;

class SetStatusRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        return Validation::player_setStatus();
    }
}

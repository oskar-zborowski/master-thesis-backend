<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function chooseAvatar(?string $name = null, bool $rand = false) {

        $avatars = [
            'AVATAR_1',
            'AVATAR_2',
            'AVATAR_3',
            'AVATAR_4',
            'AVATAR_5',
        ];

        $avatarCounter = count($avatars);

        if ($rand) {
            $number = rand(0, $avatarCounter-1);
        } else {
            if ($name === null || !in_array($name, $avatars)) {
                throw new ApiException(
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                    env('APP_DEBUG') ? __('validation.custom.wrong-avatar') : null
                );
            } else {
                $number = array_search($name, $avatars);
            }
        }

        return $avatars[$number];
    }
}

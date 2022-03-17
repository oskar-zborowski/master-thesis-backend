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

    protected function chooseAvatar(?int $number = null, bool $rand = false) {

        $avatars = [
            'AVATAR_1',
            'AVATAR_2',
            'AVATAR_3',
            'AVATAR_4',
            'AVATAR_5',
        ];

        $avatarCounter = count($avatars);

        if ($rand) {
            $number = rand(1, $avatarCounter);
        }

        if ($number > $avatarCounter) {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                env('APP_DEBUG') ? __('validation.custom.wrong-avatar-number') : null
            );
        }

        return $avatars[$number-1];
    }
}

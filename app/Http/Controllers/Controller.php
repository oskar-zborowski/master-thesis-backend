<?php

namespace App\Http\Controllers;

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

        if ($rand) {
            $avatarCounter = count($avatars);
            $number = rand(0, $avatarCounter-1);
            $name = $avatars[$number];
        }

        return $name;
    }
}

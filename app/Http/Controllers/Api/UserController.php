<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Responses\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * #### `POST` `/api/v1/users`
     * Stworzenie nowego użytkownika
     */
    public function createUser(CreateUserRequest $request) {

        $user = new User;
        $user->name = null;
        $user->default_avatar = Validation::chooseAvatar();
        $user->producer = $request->producer;
        $user->model = $request->model;
        $user->os_name = $request->os_name;
        $user->os_version = $request->os_version;
        $user->app_version = $request->app_version;
        $user->uuid = $request->uuid;
        $user->save();

        Auth::loginUsingId($user->id);

        $this->saveGpsLog($request->gps_location, $request);

        Encrypter::generateAuthTokens();
        JsonResponse::sendSuccess($request, $user->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/users/me`
     * Edycja danych użytkownika
     */
    public function updateUser(UpdateUserRequest $request) {

        /** @var User $user */
        $user = Auth::user();

        if ($request->name !== null) {
            $user->name = $request->name;
        }

        if ($request->os_version !== null) {
            $user->os_version = $request->os_version;
        }

        $user->app_version = $request->app_version;
        $user->save();

        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');

        /** @var \App\Models\GpsLog $gpsLog */
        $gpsLog = $user->gpsLogs()->where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate)->first();

        if (!$gpsLog) {
            $this->saveGpsLog($request->gps_location, $request);
        }

        JsonResponse::sendSuccess($request, $user->getData());
    }

    private function saveGpsLog(string $gpsLocation, $request) {

        Validation::checkGpsLocation($gpsLocation);

        /** @var User $user */
        $user = Auth::user();

        $command = "php {$_SERVER['DOCUMENT_ROOT']}/../artisan gps-log:save";
        $command .= " \"$gpsLocation\"";

        if (env('APP_ENV') == 'local' && env('APP_DEBUG')) {
            $command .= ' "83.8.175.174"';
        } else {
            /** @var \Illuminate\Http\Request $request */
            $command .= " \"{$request->ip()}\"";
        }

        $command .= " $user->id";
        $command .= ' >/dev/null 2>/dev/null &';

        shell_exec($command);
    }
}

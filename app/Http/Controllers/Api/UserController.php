<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Libraries\Encrypter;
use App\Http\Requests\UserRequest;
use App\Http\Responses\JsonResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * #### `POST` `/api/v1/users`
     * Stworzenie nowego użytkownika
     */
    public function createUser(UserRequest $request) {

        $user = new User;
        $user->name = $request->name;
        $user->default_avatar = $this->chooseAvatar(null, true);
        $user->producer = $request->producer;
        $user->model = $request->model;
        $user->os_name = $request->os_name;
        $user->os_version = $request->os_version;
        $user->app_version = $request->app_version;
        $user->uuid = $request->uuid;
        $user->save();

        Auth::loginUsingId($user->id);
        Encrypter::generateAuthTokens();
        JsonResponse::sendSuccess($request, $user, null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/users/me`
     * Edycja użytkownika
     */
    public function updateUser(UserRequest $request) {

        /** @var User $user */
        $user = Auth::user();
        $user->update($request->only('name', 'os_version', 'app_version'));

        JsonResponse::sendSuccess($request, $user, null);
    }

    /**
     * #### `GET` `/api/v1/users/me`
     * Pobranie danych o użytkowniku
     */
    public function getUser(Request $request) {

        /** @var User $user */
        $user = Auth::user();

        JsonResponse::sendSuccess($request, $user, null);
    }
}

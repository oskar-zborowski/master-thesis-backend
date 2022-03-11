<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;

class UserController extends Controller
{
    /**
     * #### `POST` `/api/v1/users`
     * Stworzenie nowego użytkownika
     */
    public function createUser(UserRequest $request) {
        //
    }

    /**
     * #### `PATCH` `/api/v1/users/{user}`
     * Edycja użytkownika
     */
    public function updateUser(User $user, UserRequest $request) {
        //
    }
}

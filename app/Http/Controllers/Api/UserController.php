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
     * 
     * @param UserRequest $request
     */
    public function createUser(UserRequest $request): void {
        //
    }

    /**
     * #### `PATCH` `/api/v1/users/{user}`
     * Edycja użytkownika
     * 
     * @param User $user
     * @param UserRequest $request
     */
    public function updateUser(User $user, UserRequest $request): void {
        //
    }
}

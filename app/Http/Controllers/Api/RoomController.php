<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Room;

class RoomController extends Controller
{
    /**
     * #### `POST` `/api/v1/rooms`
     * Stworzenie nowego pokoju
     */
    public function createRoom(RoomRequest $request) {
        JsonResponse::sendSuccess();
    }

    /**
     * #### `PATCH` `/api/v1/rooms/{room}`
     * Edycja pokoju
     */
    public function updateRoom(Room $room, RoomRequest $request) {
        JsonResponse::sendSuccess();
    }
}

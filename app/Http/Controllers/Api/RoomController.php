<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Room;

class RoomController extends Controller
{
    /**
     * #### `POST` `/api/v1/rooms`
     * Stworzenie nowego pokoju
     * 
     * @param RoomRequest $request
     */
    public function createRoom(RoomRequest $request): void {
        //
    }

    /**
     * #### `PATCH` `/api/v1/rooms/{room}`
     * Edycja pokoju
     * 
     * @param Room $room
     * @param RoomRequest $request
     */
    public function updateRoom(Room $room, RoomRequest $request): void {
        //
    }
}

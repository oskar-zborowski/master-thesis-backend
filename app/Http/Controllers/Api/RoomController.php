<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\JsonConfig;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    /**
     * #### `POST` `/api/v1/rooms`
     * Stworzenie nowego pokoju
     */
    public function createRoom(Request $request) {

        /** @var User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = new Room;
        $room->host_id = $user->id;
        $room->code = Encrypter::generateToken(6, Room::class, 'code');
        $room->game_config = JsonConfig::gameConfig();
        $room->save();

        JsonResponse::sendSuccess($request, null, null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/rooms/{room}`
     * Edycja pokoju
     */
    public function updateRoom(Room $room, UpdateRoomRequest $request) {
        JsonResponse::sendSuccess($request);
    }
}

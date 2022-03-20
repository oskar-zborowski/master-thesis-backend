<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Geometry;
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

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = new Room;
        $room->host_id = $user->id;
        $room->code = Encrypter::generateToken(6, Room::class, 'code');
        $room->game_config = JsonConfig::defaultGameConfig();
        $room->save();

        JsonResponse::sendSuccess($request, null, null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/rooms/{room}`
     * Edycja pokoju
     */
    public function updateRoom(Room $room, UpdateRoomRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($room->host_id != $user->id) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission')
            );
        }

        /** @var \App\Models\Player $newHost */
        $newHost = $room->players()->where('user_id', $request->host_id)->where('status', '<>', 'BLOCKED')->first();

        if ($newHost === null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.user-is-not-in-room')
            );
        }

        $room->host_id = $request->host_id;
        $room->game_mode = $request->game_mode;
        $room->game_config = JsonConfig::gameConfig($room, $request);

        if ($request->boundary !== null) {
            $room->boundary = Geometry::geometryObject($request->boundary, 'POLYGON');
        }

        if ($request->mission_centers !== null) {
            $room->mission_centers = Geometry::geometryObject($request->mission_centers, 'MULTIPOINT');
        }

        if ($request->monitoring_centers !== null) {
            $room->monitoring_centers = Geometry::geometryObject($request->monitoring_centers, 'MULTIPOINT');
        }

        if ($request->monitoring_centrals !== null) {
            $room->monitoring_centrals = Geometry::geometryObject($request->monitoring_centrals, 'MULTIPOINT');
        }

        $room->save();

        JsonResponse::sendSuccess($request);
    }

    /**
     * #### `GET` `/api/v1/rooms/{room}`
     * Pobranie informacji o grze
     */
    public function getRoom(Room $room, Request $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var \App\Models\Player $player */
        $player = $room->players()->where('user_id', $user->id)->where('status', '<>', 'BLOCKED')->first();

        if ($player === null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission')
            );
        }

        JsonResponse::sendSuccess($request);
    }
}

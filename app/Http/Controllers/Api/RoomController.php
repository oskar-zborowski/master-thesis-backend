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
use App\Models\Player;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    /**
     * #### `POST` `/api/v1/rooms`
     * Stworzenie nowego pokoju
     */
    public function createRoom(Request $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->name === null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.you-must-set-player-name'),
                __FUNCTION__
            );
        }

        /** @var Player[] $players */
        $players = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        foreach ($players as $player) {

            /** @var Room $room */
            $room = $player->room()->first();

            if ($room && $room->status != 'GAME_OVER') {
                $userInAnotherRoom = true;
            }
        }

        if (isset($userInAnotherRoom)) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.you-are-already-in-another-room'),
                __FUNCTION__
            );
        }

        $room = new Room;
        $room->host_id = $user->id;
        $room->code = Encrypter::generateToken(6, Room::class, 'code');
        $room->game_config = JsonConfig::getDefaultGameConfig();
        $room->save();

        $now = date('Y-m-d H:i:s');
        $expectedTimeAt = date('Y-m-d H:i:s', strtotime('+5 seconds', strtotime($now)));

        $player = new Player;
        $player->room_id = $room->id;
        $player->user_id = $user->id;
        $player->avatar = $user->default_avatar;
        $player->player_config = JsonConfig::getDefaultPlayerConfig();
        $player->expected_time_at = $expectedTimeAt;
        $player->save();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/rooms/my/last`
     * Edycja pokoju
     */
    public function updateRoom(UpdateRoomRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($room->host_id != $user->id) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        if ($request->host_id !== null) {

            /** @var \App\Models\Player $newHost */
            $newHost = $room->players()->where('user_id', $request->host_id)->where('status', '<>', 'BLOCKED')->first();

            if ($newHost === null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('validation.custom.user-is-not-in-room'),
                    __FUNCTION__
                );
            }

            $room->host_id = $request->host_id;
        }

        if ($request->game_mode !== null) {
            $room->game_mode = $request->game_mode;
        }

        // $room->game_config = JsonConfig::gameConfig($room, $request);

        if ($request->boundary !== null) {
            $room->boundary = DB::raw("ST_GeomFromText('POLYGON((0 0,10 0,10 10,0 10,0 0))')");
            // $room->boundary = Geometry::geometryObject($request->boundary, 'POLYGON');
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
     * #### `GET` `/api/v1/rooms/my/last`
     * Pobranie informacji o grze
     */
    public function getRoom(Request $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $room->players()->where('user_id', $user->id)->first();

        if (!$player || $player->status == 'LEFT' || $player->status == 'LEFT_PERMANENTLY') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        if ($player->status == 'BLOCKED') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.you-have-been-banned'),
                __FUNCTION__
            );
        }

        JsonResponse::sendSuccess($request, $room->getData());
    }
}

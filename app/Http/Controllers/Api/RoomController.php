<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\JsonConfig;
use App\Http\Libraries\Validation;
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

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $room */
            $room = $player->room()->first();

            if ($room && $room->status != 'GAME_OVER') {
                $userInAnotherRoom = true;
            }

            if (isset($userInAnotherRoom)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.you-are-already-in-another-room'),
                    __FUNCTION__
                );
            }
        }

        $room = new Room;
        $room->host_id = $user->id;
        $room->code = Encrypter::generateToken(6, Room::class, 'code');
        $room->config = JsonConfig::getDefaultGameConfig();
        $room->save();

        $now = date('Y-m-d H:i:s');
        $expectedTimeAt = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime($now)));

        $player = new Player;
        $player->room_id = $room->id;
        $player->user_id = $user->id;
        $player->avatar = $user->default_avatar;
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

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $room */
            $room = $player->room()->first();

            if (!$room || $room->host_id != $user->id) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                    false
                );
            }

        } else {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($request->host_id !== null) {

            /** @var \App\Models\Player $newHost */
            $newHost = $room->players()->where('user_id', $request->host_id)->whereNotIn('status', ['BLOCKED', 'LEFT'])->first();

            if (!$newHost) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(false, true),
                    __('validation.custom.user-is-not-in-room'),
                    __FUNCTION__
                );
            }

            $room->host_id = $request->host_id;
        }

        if ($request->is_code_renewal !== null && $request->is_code_renewal) {

            $newCode = Encrypter::generateToken(6, Room::class, 'code');

            $encryptedOldCode = Encrypter::encrypt($room->code, 6, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('code', $encryptedOldCode);

            /** @var Room[] $oldRooms */
            $oldRooms = Room::whereRaw($aesDecrypt)->where('id', '!=', $room->id)->get();

            foreach ($oldRooms as $oldRoom) {
                $oldRoom->code = $newCode;
                $oldRoom->save();
            }

            $room->code = $newCode;
        }

        $room->config = JsonConfig::setGameConfig($room, $request);

        if ($request->boundary_points !== null) {

            Validation::checkBoundary($request->boundary_points);

            $room->boundary_polygon = DB::raw("ST_GeomFromText('POLYGON(({$request->boundary_points}))')");
            $room->boundary_points = $request->boundary_points;
        }

        $room->save();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `GET` `/api/v1/rooms/my/last`
     * Pobranie informacji o grze
     */
    public function getRoom(Request $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $room */
            $room = $player->room()->first();

            if (!$room && $room->status == 'GAME_OVER') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                );
            }

        } else {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        JsonResponse::sendSuccess($request, $room->getData());
    }
}

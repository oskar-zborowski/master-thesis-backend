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

            if ($room->status != 'GAME_OVER') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-are-already-in-another-room'),
                    __FUNCTION__
                );
            }
        }

        $room = new Room;
        $room->host_id = $user->id;
        $room->group_code = Encrypter::generateToken(11, Room::class, 'group_code', true);
        $room->code = Encrypter::generateToken(6, Room::class, 'code', true);
        $room->config = JsonConfig::getDefaultGameConfig();
        $room->save();

        $player = new Player;
        $player->room_id = $room->id;
        $player->user_id = $user->id;
        $player->avatar = $user->default_avatar;
        $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
        $player->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/rooms/my/last`
     * Edycja pokoju (endpoint tylko dla hosta)
     */
    public function updateRoom(UpdateRoomRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if (!$player) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($request->host_id !== null) {

            /** @var Player $newHost */
            $newHost = $room->players()->where([
                'user_id' => $request->host_id,
                'status' => 'CONNECTED',
            ])->first();

            if (!$newHost) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.user-is-not-in-room'),
                    __FUNCTION__
                );
            }

            $room->host_id = $request->host_id;
        }

        if ($request->is_code_renewal) {

            $newCode = Encrypter::generateToken(6, Room::class, 'code', true);

            $encryptedOldGroupCode = Encrypter::encrypt($room->group_code, 11, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('group_code', $encryptedOldGroupCode);

            /** @var Room[] $oldRooms */
            $oldRooms = Room::whereRaw($aesDecrypt)->where('id', '!=', $room->id)->get();

            foreach ($oldRooms as $oldRoom) {
                $oldRoom->code = $newCode;
                $oldRoom->save();
            }

            $room->code = $newCode;
        }

        if ($request->is_set_initial_settings) {
            $room->config = JsonConfig::getDefaultGameConfig();
        }

        if ($request->is_cleaning_roles) {

            /** @var Player[] $players */
            $players = $room->players()->get();

            foreach ($players as $player) {
                $player->role = null;
                $player->save();
            }
        }

        $room->config = JsonConfig::setGameConfig($room, $request);

        if ($request->boundary_points !== null) {

            Validation::checkBoundary($request->boundary_points);

            $room->boundary_polygon = DB::raw("ST_GeomFromText('POLYGON(($request->boundary_points))')");
            $room->boundary_points = $request->boundary_points;
        }

        $room->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }
}

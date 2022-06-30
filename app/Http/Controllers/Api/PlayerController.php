<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Http\Requests\CreatePlayerRequest;
use App\Http\Requests\SetRoleRequest;
use App\Http\Requests\SetStatusRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(CreatePlayerRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->name === null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.you-must-set-player-name'),
                __FUNCTION__
            );
        }

        $encryptedCode = Encrypter::encrypt($request->code, 6, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('code', $encryptedCode);

        /** @var Room $room */
        $room = Room::whereRaw($aesDecrypt)->orderBy('id', 'desc')->first();

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $lastRoom */
            $lastRoom = $player->room()->first();

            if ($lastRoom->status != 'GAME_OVER' && (!$room || $room->id != $lastRoom->id)) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-are-already-in-another-room'),
                    __FUNCTION__
                );
            }
        }

        if (!$room) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.incorrect-code'),
                __FUNCTION__
            );
        }

        /** @var Player $player */
        $player = $room->players()->where('user_id', $user->id)->first();

        if ($player) {

            if ($player->status == 'BANNED') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-have-been-banned'),
                    __FUNCTION__
                );

            } else if ($player->status == 'LEFT') {

                if ($room->status != 'WAITING_IN_ROOM') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-already-started'),
                        __FUNCTION__
                    );
                }

                $avatar = $player->avatar;

                /** @var Player $isAvatarExists */
                $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

                if ($isAvatarExists) {
                    $avatar = $this->findAvailableAvatar($room);
                }

                $player->avatar = $avatar;
                $player->role = null;
                $player->status = 'CONNECTED';
                $player->save();

                $user->default_avatar = $avatar;
                $user->save();

            } else if ($player->status == 'DISCONNECTED') {
                $player->status = 'CONNECTED';
                $player->save();
            }

            if ($room->status == 'GAME_OVER') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.game-is-over'),
                    __FUNCTION__
                );
            }

        } else if ($room->status != 'WAITING_IN_ROOM') {

            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.game-already-started'),
                __FUNCTION__
            );

        } else {

            $now = date('Y-m-d H:i:s');
            $expectedTimeAt = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime($now)));

            $player = new Player;
            $player->room_id = $room->id;
            $player->user_id = $user->id;

            $avatar = $user->default_avatar;

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

            if ($isAvatarExists) {
                $avatar = $this->findAvailableAvatar($room);
            }

            $player->avatar = $avatar;
            $player->expected_time_at = $expectedTimeAt;
            $player->save();

            $user->default_avatar = $avatar;
            $user->save();
        }

        $room->refresh();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/players/my/last`
     * Edycja danych gracza (zmiana parametrów podczas oczekiwania w pokoju i w trakcie gry)
     */
    public function updatePlayer(UpdatePlayerRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

        if (!$player) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        /** @var Room $room */
        $room = $player->room()->first();

        if ($room->status == 'GAME_IN_PROGRESS') {

            Validation::checkGpsLocation($request->gps_location);

            if (!in_array($player->role, ['THIEF', 'AGENT'])) {
                $player->global_position = DB::raw("ST_GeomFromText('POINT({$request->gps_location})')");
            }

            $player->hidden_position = DB::raw("ST_GeomFromText('POINT({$request->gps_location})')");
        }

        if ($request->avatar) {

            if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            /** @var Player $avatarExists */
            $avatarExists = $room->players()->where('avatar', $request->avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

            if ($avatarExists) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.avatar-busy'),
                    __FUNCTION__
                );
            }

            $player->avatar = $request->avatar;

            $user->default_avatar = $request->avatar;
            $user->save();
        }

        if ($request->status) {
            $player->status = $request->status;
        }

        $player->save();

        $room->refresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/status`
     * Ustawienie statusu gracza (endpoint tylko dla hosta, host nie może zmieniać swojego statusu - musi skorzystać w tym celu z updatePlayer)
     */
    public function setStatus(Player $player, SetStatusRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $user->id == $player->user_id || $room->voting_type == 'START' ||
            !in_array($room->status, ['WAITING_IN_ROOM', 'GAME_PAUSED']) ||
            in_array($player->status, ['CONNECTED', 'DISCONNECTED']) && $request->status == 'LEFT')
        {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($room->status == 'WAITING_IN_ROOM') {
            $player->role = null;
        }

        $player->status = $request->status;
        $player->save();

        $room->refresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/role`
     * Ustawienie roli gracza (endpoint tylko dla hosta)
     */
    public function setRole(Player $player, SetRoleRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $room->voting_type == 'START' ||
            $room->status != 'WAITING_IN_ROOM' || $room->config['other']['is_role_random'])
        {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if (!in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        /** @var Player[] $players */
        $players = $room->players()->where('role', $request->role)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
        $playersNumber = count($players);

        if ($request->role !== null && $request->role != $player->role &&
            $playersNumber >= $room->config['actor'][strtolower($request->role)]['number'])
        {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.max-player-number-reached'),
                __FUNCTION__
            );
        }

        $player->role = $request->role;
        $player->save();

        $room->refresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    private function findAvailableAvatar(Room $room) {

        $i = 0;
        $avatars = Validation::getAvatars();

        do {

            $avatar = $avatars[$i++];

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

        } while ($isAvatarExists);

        return $avatar;
    }
}

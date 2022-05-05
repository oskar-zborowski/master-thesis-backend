<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\JsonConfig;
use App\Http\Requests\CreatePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(CreatePlayerRequest $request) {

        /** @var Room $room */
        $room = Room::where('code', $request->code)->first();

        if (!$room) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.incorrect-code'),
                __FUNCTION__
            );
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $room->players()->where('user_id', $user->id)->first();

        if ($player) {
            if ($player->status == 'BLOCKED') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-have-been-banned'),
                    __FUNCTION__
                );
            } else {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.you-are-already-in-room'),
                    __FUNCTION__
                );
            }
        }

        if ($room->status != 'WAITING_IN_ROOM') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.game-already-started'),
                __FUNCTION__
            );
        }

        $now = date('Y-m-d H:i:s');
        $expirationDate = date('Y-m-d H:i:s', strtotime('+5 seconds', strtotime($now)));

        /** @var Player $player */
        $player = new Player;
        $player->room_id = $room->id;
        $player->user_id = $user->id;
        $player->avatar = $user->default_avatar;
        $player->player_config = JsonConfig::getDefaultPlayerConfig();
        $player->expected_time_at = $expirationDate;
        $player->save();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/players/{player}`
     * Edycja danych gracza (zmiana parametrów podczas oczekiwania w pokoju i w trakcie gry)
     */
    public function updatePlayer(Player $player, UpdatePlayerRequest $request) {

        /** @var Room $room */
        $room = $player->room()->first();

        JsonResponse::sendSuccess($request, $room->getData());
    }
}

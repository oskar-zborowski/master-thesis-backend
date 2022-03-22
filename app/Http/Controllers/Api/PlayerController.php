<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Player;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(PlayerRequest $request) {
        JsonResponse::sendSuccess($request, null, null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/players/{player}`
     * Edycja gracza (zmiana parametrów podczas oczekiwania w pokoju i w trakcie gry)
     */
    public function updatePlayer(Player $player, PlayerRequest $request) {
        JsonResponse::sendSuccess($request);
    }
}

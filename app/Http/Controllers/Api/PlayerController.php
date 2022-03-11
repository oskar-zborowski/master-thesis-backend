<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRequest;
use App\Models\Player;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(PlayerRequest $request) {
        //
    }

    /**
     * #### `PATCH` `/api/v1/players/{player}`
     * Edycja gracza (zmiana parametrów w trakcie gry)
     */
    public function updatePlayer(Player $player, PlayerRequest $request) {
        //
    }
}

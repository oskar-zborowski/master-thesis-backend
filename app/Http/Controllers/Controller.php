<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function savePing(Player $player) {
        if ($player->status == 'CONNECTED' && now() >= $player->expected_time_at) {
            $timeDifference = strtotime(now()) - strtotime($player->expected_time_at);
            $player->ping = $timeDifference;
            $player->average_ping = ($player->average_ping * $player->samples_number + $timeDifference) / ($player->samples_number + 1);
            $player->samples_number = $player->samples_number + 1;
            $player->save();
        }
    }
}

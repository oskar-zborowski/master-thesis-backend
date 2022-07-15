<?php

namespace App\Http\Libraries;

use App\Models\Player;
use App\Models\Room;

/**
 * Klasa przechowująca wszystkie pomocnicze metody
 */
class Other
{
    public static function createNewRoom(Room $room) {

        $newRoom = new Room;
        $newRoom->host_id = $room->host_id;

        if ($room->counter < 255) {
            $newRoom->group_code = $room->group_code;
            $newRoom->code = $room->code;
            $newRoom->counter = $room->counter + 1;
        } else {
            $newRoom->group_code = Encrypter::generateToken(11, Room::class, 'group_code', true);
            $newRoom->code = Encrypter::generateToken(6, Room::class, 'code', true);
        }

        $newRoom->config = $room->config;
        $newRoom->config['duration']['real'] = 0;
        $newRoom->boundary_polygon = $room->boundary_polygon;
        $newRoom->boundary_points = $room->boundary_points;
        $newRoom->save();

        $room->boundary_polygon = null;
        $room->save();

        /** @var Player[] $players */
        $players = $room->players()->get();

        foreach ($players as $player) {

            if (!$player->is_bot && $player->status == 'CONNECTED') {
                $newPlayer = new Player;
                $newPlayer->room_id = $newRoom->id;
                $newPlayer->user_id = $player->user_id;
                $newPlayer->avatar = $player->avatar;
                $newPlayer->role = $player->role;
                $newPlayer->save();
            }

            $player->global_position = null;
            $player->hidden_position = null;
            $player->fake_position = null;
            $player->status = 'LEFT';
            $player->save();
        }
    }

    public static function setNewHost(Room $room) {

        /** @var Player[] $newHosts */
        $newHosts = $room->players()->where('status', 'CONNECTED')->get();

        if (!empty($newHosts)) {

            $newHostUserId = null;
            $newHostsNumber = count($newHosts);
            $randNewHost = rand(1, $newHostsNumber);

            foreach ($newHosts as $newHost) {

                $randNewHost--;

                if ($randNewHost == 0) {
                    $newHostUserId = $newHost->user_id;
                    break;
                }
            }

            $room->host_id = $newHostUserId;
            $room->save();
        }
    }
}

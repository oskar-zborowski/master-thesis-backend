<?php

namespace App\Console\Commands;

use App\Http\Libraries\Other;
use App\Models\Room;
use Illuminate\Console\Command;

class CheckRoom extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'room:check {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Check the room';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        do {

            sleep(env('ROOM_CHECK_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            /** @var \App\Models\Player[] $players */
            $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

            $now = now();

            foreach ($players as $player) {

                if ($player->disconnecting_finished_at === null && $now > date('Y-m-d H:i:s', strtotime('+' . env('DISCONNECTING_TIMEOUT') . ' seconds', strtotime($player->expected_time_at)))) {

                    $player->status = 'DISCONNECTED';

                    if ($room->config['other']['disconnecting_countdown'] != -1) {
                        $player->disconnecting_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['other']['disconnecting_countdown'] . ' seconds', strtotime($now)));
                    }

                    $player->save();

                    if ($player->user_id == $room->host_id) {
                        Other::setNewHost($room);
                    }
                }

                if ($player->disconnecting_finished_at && $now > $player->disconnecting_finished_at) {
                    $player->global_position = null;
                    $player->hidden_position = null;
                    $player->fake_position = null;
                    $player->is_catching = false;
                    $player->is_caughting = false;
                    $player->is_crossing_boundary = false;
                    $player->voting_answer = null;
                    $player->status = 'LEFT';
                    $player->failed_voting_type = null;
                    $player->black_ticket_finished_at = null;
                    $player->fake_position_finished_at = null;
                    $player->disconnecting_finished_at = null;
                    $player->crossing_boundary_finished_at = null;
                    $player->speed_exceeded_at = null;
                    $player->next_voting_starts_at = null;
                    $player->save();
                }
            }

            if (count($players) == 0) {
                $room->delete();
                $emptyRoom = true;
            }

        } while (!isset($emptyRoom) && in_array($room->status, ['WAITING_IN_ROOM', 'GAME_PAUSED']));
    }
}

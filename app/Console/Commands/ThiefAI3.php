<?php

namespace App\Console\Commands;

use App\Http\Libraries\ThiefAI;
use App\Models\Room;
use Illuminate\Console\Command;

class ThiefAi3 extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thief-ai3:start {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Start the Thief AI';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        /** @var Room $room */
        $room = Room::where('id', $roomId)->first();

        if (!$room || $room->status != 'GAME_IN_PROGRESS') {
            die;
        }

        $spawnBotsStatus = ThiefAI::spawnBots($room);

        if (!$spawnBotsStatus) {
            ThiefAI::spawnBots($room, true);
        }

        do {

            sleep(env('BOT_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if (!$room || $room->status != 'GAME_IN_PROGRESS') {
                break;
            }

        } while ($room->status == 'GAME_IN_PROGRESS');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckGameCourse extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'game-course:check {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Check the course of the game';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        do {

            sleep(env('GAME_COURSE_CHECK_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            /** @var Player[] $thieves */
            $thieves = $room->players()->where('role', 'THIEF')->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

            foreach ($thieves as $thief) {

                $thiefCaught = DB::select(DB::raw("SELECT id FROM players WHERE room_id == $room->id AND status == 'CONNECTED' AND role <> 'THIEF' AND ST_Distance_Sphere($thief->hidden_position, hidden_position) <= {$room->config['actor']['policeman']['catching']['radius']}"));

                if (count($thiefCaught) >= $room->config['actor']['policeman']['catching']['radius']) {
                    $thief->is_caughting = false;
                    $thief->caught_at = now();
                } else if (!empty($thiefCaught)) {
                    $thief->is_caughting = true;
                }

                $thief->save();
            }

        } while ($room->status == 'GAME_IN_PROGRESS');

        return 0;
    }
}

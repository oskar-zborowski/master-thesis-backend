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

            $gameStarted = false;
            $revealThieves = false;
            $now = now();

            if ($now >= $room->game_started_at) {
                $gameStarted = true;
            }

            if ($gameStarted) {

                if ($now >= $room->next_disclosure_at) {
                    $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($now)));
                    $room->save();
                    $revealThieves = true;
                }

                foreach ($thieves as $thief) {

                    $thiefSave = false;

                    if ($revealThieves) {

                        if ($thief->black_ticket_finished_at === null || $now > $thief->black_ticket_finished_at) {

                            if ($thief->black_ticket_finished_at && $now > $thief->black_ticket_finished_at) {
                                $thief->black_ticket_finished_at = null;
                                $thiefSave = true;
                            }

                            if ($thief->fake_position_finished_at && $now <= $thief->fake_position_finished_at) {

                                if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                                    $disclosureThief = DB::select(DB::raw("SELECT id FROM players WHERE room_id == $room->id AND status == 'CONNECTED' AND role <> 'THIEF' AND ST_Distance_Sphere($thief->fake_position, hidden_position) <= {$room->config['actor']['policeman']['visibility_radius']}"));

                                    if (!empty($disclosureThief)) {
                                        $thief->global_position = $thief->fake_position;
                                        $thiefSave = true;
                                    }

                                } else {
                                    $thief->global_position = $thief->fake_position;
                                    $thiefSave = true;
                                }

                            } else {

                                if ($thief->fake_position_finished_at && $now > $thief->fake_position_finished_at) {
                                    $thief->fake_position = null;
                                    $thief->fake_position_finished_at = null;
                                    $thiefSave = true;
                                }

                                $thief->global_position = $thief->hidden_position;
                                $thiefSave = true;
                            }
                        }
                    }

                    $thiefCaught = DB::select(DB::raw("SELECT id FROM players WHERE room_id == $room->id AND status == 'CONNECTED' AND role <> 'THIEF' AND ST_Distance_Sphere($thief->hidden_position, hidden_position) <= {$room->config['actor']['policeman']['catching']['radius']}"));

                    if (count($thiefCaught) >= $room->config['actor']['policeman']['catching']['radius']) {
                        $thief->is_caughting = false;
                        $thief->caught_at = now();
                        $thiefSave = true;
                    } else if (!empty($thiefCaught)) {
                        $thief->is_caughting = true;
                        $thiefSave = true;
                    }

                    if ($thiefSave) {
                        $thief->save();
                    }
                }
            }

        } while ($room->status == 'GAME_IN_PROGRESS');

        return 0;
    }
}

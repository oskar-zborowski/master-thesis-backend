<?php

namespace App\Console\Commands;

use App\Http\Libraries\Other;
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

            /** @var \App\Models\Player[] $players */
            $players = $room->players()->get();

            $gameStarted = false;
            $revealThieves = false;
            $now = now();

            if ($now >= $room->game_started_at) {
                $gameStarted = true;
            }

            if ($gameStarted) {

                if ($now >= $room->game_ended_at) {

                    $room->reporting_user_id = null;
                    $room->config['duration']['real'] = $room->config['duration']['scheduled'];
                    $room->status = 'GAME_OVER';
                    $room->game_result = 'THIEVES_WON_ON_TIME';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    foreach ($players as $player) {

                        if (in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {
                            $player->global_position = $player->hidden_position;
                        }

                        $player->voting_answer = null;
                        $player->black_ticket_finished_at = null;
                        $player->fake_position_finished_at = null;
                        $player->disconnecting_finished_at = null;
                        $player->crossing_boundary_finished_at = null;
                        $player->next_voting_starts_at = null;
                        $player->save();
                    }

                    sleep(env('GAME_OVER_PAUSE'));
                    Other::createNewRoom($room);

                    break;
                }

                if ($now >= $room->next_disclosure_at) {
                    $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($now)));
                    $room->save();
                    $revealThieves = true;
                }

                $thievesNotCaught = 0;

                foreach ($players as $thief) {

                    if ($thief->role == 'THIEF' && $thief->caught_at === null && in_array($thief->status, ['CONNECTED', 'DISCONNECTED'])) {

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

                        if (count($thiefCaught) >= $room->config['actor']['policeman']['catching']['number']) {
                            $thief->is_caughting = false;
                            $thief->caught_at = now();
                            $thiefSave = true;
                        } else if (!empty($thiefCaught)) {
                            $thief->is_caughting = true;
                            $thievesNotCaught++;
                            $thiefSave = true;
                        } else {
                            $thievesNotCaught++;
                        }

                        if ($thiefSave) {
                            $thief->save();
                        }
                    }
                }

                if ($thievesNotCaught == 0) {

                    $room->reporting_user_id = null;

                    if ($now <= $room->game_ended_at) {
                        $room->config['duration']['real'] = strtotime($room->config['duration']['scheduled']) + strtotime($now) - strtotime($room->game_ended_at);
                    } else {
                        $room->config['duration']['real'] = $room->config['duration']['scheduled'];
                    }

                    $room->status = 'GAME_OVER';
                    $room->game_result = 'POLICEMEN_WON_BY_CATCHING';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    foreach ($players as $player) {

                        if (in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {
                            $player->global_position = $player->hidden_position;
                        }

                        $player->voting_answer = null;
                        $player->black_ticket_finished_at = null;
                        $player->fake_position_finished_at = null;
                        $player->disconnecting_finished_at = null;
                        $player->crossing_boundary_finished_at = null;
                        $player->next_voting_starts_at = null;
                        $player->save();
                    }

                    sleep(env('GAME_OVER_PAUSE'));
                    Other::createNewRoom($room);

                    break;
                }
            }

        } while ($room->status == 'GAME_IN_PROGRESS');

        return 0;
    }
}

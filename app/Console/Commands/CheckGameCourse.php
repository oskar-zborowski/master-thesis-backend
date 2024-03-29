<?php

namespace App\Console\Commands;

use App\Http\Libraries\Other;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

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

        /** @var Player[] $allPlayers */
        $allPlayers = Player::where([
            'room_id' => $roomId,
            'is_bot' => false,
            'status' => 'CONNECTED',
        ])->get();

        $allPlayersNumber = count($allPlayers);

        do {

            sleep(env('GAME_COURSE_CHECK_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if ($room->status != 'GAME_IN_PROGRESS') {
                break;
            }

            /** @var \App\Models\Player $host */
            $host = $room->players()->where('user_id', $room->host_id)->first();

            if ($host && $host->status != 'CONNECTED') {
                Other::setNewHost($room);
            }

            /** @var Player[] $players */
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

                    $tempConfig = $room->config;
                    $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                    $room->config = $tempConfig;

                    $room->status = 'GAME_OVER';
                    $room->game_result = 'THIEVES_WON_ON_TIME';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    foreach ($players as $player) {

                        if (in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {

                            $player->mergeCasts([
                                'global_position' => Point::class,
                                'hidden_position' => Point::class,
                            ]);

                            $player->global_position = $player->hidden_position;
                        }

                        $player->is_catching = false;
                        $player->is_caughting = false;
                        $player->voting_answer = null;
                        $player->failed_voting_type = null;
                        $player->black_ticket_finished_at = null;
                        $player->fake_position_finished_at = null;
                        $player->disconnecting_finished_at = null;
                        $player->crossing_boundary_finished_at = null;
                        $player->speed_exceeded_at = null;
                        $player->next_voting_starts_at = null;
                        $player->save();
                    }

                    sleep(env('GAME_OVER_PAUSE'));
                    Other::createNewRoom($room);

                    break;
                }

                if ($room->next_disclosure_at !== null && $now >= $room->next_disclosure_at) {
                    if ($room->config['actor']['thief']['disclosure_interval'] != -1) {
                        $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($now)));
                        $room->save();
                        $revealThieves = true;
                    }
                }

                $thievesNotCaught = 0;
                $activePlayersNumber = 0;

                $catchers = [];
                $disclosedPolicemen = [];

                foreach ($players as $player) {

                    if ($player->speed_exceeded_at && $now > date('Y-m-d H:i:s', strtotime('+' . env('SPEED_EXCEEDED_TIMEOUT') . ' seconds', strtotime($player->speed_exceeded_at)))) {
                        $player->speed_exceeded_at = null;
                        $player->save();
                    }

                    if (in_array($player->status, ['CONNECTED', 'DISCONNECTED']) && $player->is_bot == false) {

                        $playerLeft = false;

                        if ($player->disconnecting_finished_at === null && $now > date('Y-m-d H:i:s', strtotime('+' . env('DISCONNECTING_TIMEOUT') . ' seconds', strtotime($player->expected_time_at)))) {

                            $player->status = 'DISCONNECTED';

                            if ($room->config['other']['warning_number'] != -1) {
                                $player->warning_number = $player->warning_number + 1;
                            }

                            if ($room->config['other']['disconnecting_countdown'] != -1) {
                                $player->disconnecting_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['other']['disconnecting_countdown'] . ' seconds', strtotime($now)));
                            }

                            $player->save();

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }
                        }

                        if ($room->config['other']['warning_number'] != -1 && $player->warning_number > $room->config['other']['warning_number']) {

                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
                            $player->is_catching = false;
                            $player->is_caughting = false;
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

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }

                            $playerLeft = true;
                        }

                        if ($player->disconnecting_finished_at && $now > $player->disconnecting_finished_at || $player->crossing_boundary_finished_at && $now > $player->crossing_boundary_finished_at) {

                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
                            $player->is_catching = false;
                            $player->is_caughting = false;
                            $player->voting_answer = null;
                            $player->status = 'LEFT';
                            $player->failed_voting_type = null;
                            $player->warning_number = $room->config['other']['warning_number'] + 1;
                            $player->black_ticket_finished_at = null;
                            $player->fake_position_finished_at = null;
                            $player->disconnecting_finished_at = null;
                            $player->crossing_boundary_finished_at = null;
                            $player->speed_exceeded_at = null;
                            $player->next_voting_starts_at = null;
                            $player->save();

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }

                            $playerLeft = true;
                        }

                        if (!$playerLeft) {
                            $activePlayersNumber++;
                        }

                    } else if ($player->status == 'SUPERVISING') {
                        $activePlayersNumber++;
                    }

                    if ($player->role == 'THIEF' && $player->caught_at === null && in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {

                        $thief = $player;
                        $thiefSave = false;

                        $thief->mergeCasts([
                            'global_position' => Point::class,
                            'hidden_position' => Point::class,
                            'fake_position' => Point::class,
                        ]);

                        if ($revealThieves) {

                            if ($thief->black_ticket_finished_at === null || $now > $thief->black_ticket_finished_at) {

                                if ($thief->black_ticket_finished_at && $now > $thief->black_ticket_finished_at) {
                                    $thief->black_ticket_finished_at = null;
                                    $thiefSave = true;
                                }

                                if ($thief->fake_position_finished_at && $now <= $thief->fake_position_finished_at) {

                                    if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                                        $fakePosition = "{$thief->fake_position->longitude} {$thief->fake_position->latitude}";

                                        $singlePolicemanVisibilityRadius = 1 * $room->config['actor']['policeman']['visibility_radius'];
                                        $doublePolicemanVisibilityRadius = 2 * $room->config['actor']['policeman']['visibility_radius'];

                                        $disclosureThiefByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($fakePosition)'), hidden_position) <= $singlePolicemanVisibilityRadius"));
                                        $disclosureThiefByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($fakePosition)'), hidden_position) <= $doublePolicemanVisibilityRadius"));

                                        if (count($disclosureThiefByPoliceman) > 0 || count($disclosureThiefByEagle) > 0) {
                                            $thief->global_position = $thief->fake_position;
                                            $thief->last_disclosure_at = now();
                                            $thiefSave = true;
                                        }

                                    } else {
                                        $thief->global_position = $thief->fake_position;
                                        $thief->last_disclosure_at = now();
                                        $thiefSave = true;
                                    }

                                } else {

                                    if ($thief->fake_position_finished_at && $now > $thief->fake_position_finished_at) {
                                        $thief->fake_position = null;
                                        $thief->fake_position_finished_at = null;
                                        $thiefSave = true;
                                    }

                                    if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                                        if ($thief->hidden_position) {

                                            $hiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                                            $singlePolicemanVisibilityRadius = 1 * $room->config['actor']['policeman']['visibility_radius'];
                                            $doublePolicemanVisibilityRadius = 2 * $room->config['actor']['policeman']['visibility_radius'];

                                            $disclosureThiefByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $singlePolicemanVisibilityRadius"));
                                            $disclosureThiefByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $doublePolicemanVisibilityRadius"));

                                            if (count($disclosureThiefByPoliceman) > 0 || count($disclosureThiefByEagle) > 0) {
                                                $thief->global_position = $thief->hidden_position;
                                                $thief->last_disclosure_at = now();
                                                $thiefSave = true;
                                            }
                                        }

                                    } else {
                                        $thief->global_position = $thief->hidden_position;
                                        $thief->last_disclosure_at = now();
                                        $thiefSave = true;
                                    }
                                }
                            }
                        }

                        if ($thief->hidden_position) {

                            $hiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                                $singleThiefVisibilityRadius = 1 * $room->config['actor']['thief']['visibility_radius'];

                                $policemenInRange = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status IN ('CONNECTED', 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $singleThiefVisibilityRadius"));
                                $policemenInRangeArray = [];

                                foreach ($policemenInRange as $policemanInRange) {
                                    $policemenInRangeArray[] = $policemanInRange->id;
                                }

                                if (count($policemenInRangeArray) > 0) {

                                    /** @var Player[] $policemen */
                                    $policemen = $room->players()->whereIn('id', $policemenInRangeArray)->get();

                                    foreach ($policemen as $policeman) {

                                        if (!in_array($policeman->id, $disclosedPolicemen)) {

                                            $policeman->mergeCasts([
                                                'global_position' => Point::class,
                                                'hidden_position' => Point::class,
                                            ]);

                                            $disclosedPolicemen[] = $policeman->id;
                                            $policeman->global_position = $policeman->hidden_position;
                                            $policeman->save();
                                        }
                                    }
                                }
                            }

                            $singlePolicemanCatchingRadius = 1 * $room->config['actor']['policeman']['catching']['radius'];
                            $doublePolicemanCatchingRadius = 2 * $room->config['actor']['policeman']['catching']['radius'];

                            $thiefCaughtByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role <> 'THIEF' AND role <> 'EAGLE' AND role <> 'FATTY_MAN' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $singlePolicemanCatchingRadius"));
                            $thiefCaughtByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $doublePolicemanCatchingRadius"));
                            $thiefCaughtByFattyMan = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'FATTY_MAN' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= $singlePolicemanCatchingRadius"));

                            if (count($thiefCaughtByPoliceman) + count($thiefCaughtByEagle) + 2 * count($thiefCaughtByFattyMan) >= $room->config['actor']['policeman']['catching']['number']) {
                                $thief->is_caughting = false;
                                $thief->caught_at = now();
                                $thief->global_position = $thief->hidden_position;
                                $thiefSave = true;
                            } else if (count($thiefCaughtByPoliceman) > 0 || count($thiefCaughtByEagle) > 0 || count($thiefCaughtByFattyMan) > 0) {

                                $allPolicemenIdArray = [];

                                foreach ($thiefCaughtByPoliceman as $thiefCaughtByPoliceman2) {
                                    $allPolicemenIdArray[] = $thiefCaughtByPoliceman2->id;
                                }

                                foreach ($thiefCaughtByEagle as $thiefCaughtByEagle2) {
                                    $allPolicemenIdArray[] = $thiefCaughtByEagle2->id;
                                }

                                foreach ($thiefCaughtByFattyMan as $thiefCaughtByFattyMan2) {
                                    $allPolicemenIdArray[] = $thiefCaughtByFattyMan2->id;
                                }

                                if (count($allPolicemenIdArray) > 0) {

                                    /** @var Player[] $policemen */
                                    $policemen = $room->players()->whereIn('id', $allPolicemenIdArray)->get();

                                    foreach ($policemen as $policeman) {
                                        if (!in_array($policeman->id, $catchers)) {
                                            $catchers[] = $policeman->id;
                                            $policeman->is_catching = true;
                                            $policeman->save();
                                        }
                                    }
                                }

                                $thief->is_caughting = true;
                                $thievesNotCaught++;
                                $thiefSave = true;

                            } else if ($thief->is_caughting) {
                                $thief->is_caughting = false;
                                $thievesNotCaught++;
                                $thiefSave = true;
                            } else {
                                $thievesNotCaught++;
                            }
                        }

                        if (isset($thiefSave) && $thiefSave) {
                            $thief->save();
                        }
                    }
                }

                if (count($catchers) > 0) {
                    /** @var Player[] $policemen */
                    $policemen = $room->players()->whereNotIn('id', $catchers)->where([
                        'status' => 'CONNECTED',
                        'is_catching' => true,
                    ])->get();
                } else {
                    /** @var Player[] $policemen */
                    $policemen = $room->players()->where([
                        'status' => 'CONNECTED',
                        'is_catching' => true,
                    ])->get();
                }

                foreach ($policemen as $policeman) {
                    $policeman->is_catching = false;
                    $policeman->save();
                }

                if ($thievesNotCaught == 0) {

                    $room->reporting_user_id = null;

                    $tempConfig = $room->config;

                    if ($now < $room->game_ended_at) {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'] + strtotime($now) - strtotime($room->game_ended_at);
                    } else {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                    }

                    $room->config = $tempConfig;
                    $room->status = 'GAME_OVER';
                    $room->game_result = 'POLICEMEN_WON_BY_CATCHING';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    foreach ($players as $player) {

                        if (in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {

                            $player->mergeCasts([
                                'global_position' => Point::class,
                                'hidden_position' => Point::class,
                            ]);

                            $player->global_position = $player->hidden_position;
                        }

                        $player->is_catching = false;
                        $player->is_caughting = false;
                        $player->voting_answer = null;
                        $player->failed_voting_type = null;
                        $player->black_ticket_finished_at = null;
                        $player->fake_position_finished_at = null;
                        $player->disconnecting_finished_at = null;
                        $player->crossing_boundary_finished_at = null;
                        $player->speed_exceeded_at = null;
                        $player->next_voting_starts_at = null;
                        $player->save();
                    }

                    sleep(env('GAME_OVER_PAUSE'));
                    Other::createNewRoom($room);

                    break;
                }

                if ($activePlayersNumber == 0) {

                    $tempConfig = $room->config;

                    if ($now < $room->game_ended_at) {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'] + strtotime($now) - strtotime($room->game_ended_at);
                    } else {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                    }

                    $room->config = $tempConfig;
                    $room->reporting_user_id = null;
                    $room->status = 'GAME_OVER';
                    $room->game_result = 'UNFINISHED';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    /** @var Player[] $players */
                    $players = $room->players()->get();

                    foreach ($players as $player) {
                        $player->global_position = null;
                        $player->hidden_position = null;
                        $player->fake_position = null;
                        $player->is_catching = false;
                        $player->is_caughting = false;
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

                    break;
                }

            } else {

                $activePlayersNumber = 0;

                foreach ($players as $player) {

                    if ($player->speed_exceeded_at && $now > date('Y-m-d H:i:s', strtotime('+' . env('SPEED_EXCEEDED_TIMEOUT') . ' seconds', strtotime($player->speed_exceeded_at)))) {
                        $player->speed_exceeded_at = null;
                        $player->save();
                    }

                    if (in_array($player->status, ['CONNECTED', 'DISCONNECTED']) && $player->is_bot == false) {

                        $playerLeft = false;

                        if ($player->disconnecting_finished_at === null && $now > date('Y-m-d H:i:s', strtotime('+' . env('DISCONNECTING_TIMEOUT') . ' seconds', strtotime($player->expected_time_at)))) {

                            $player->status = 'DISCONNECTED';

                            if ($room->config['other']['warning_number'] != -1) {
                                $player->warning_number = $player->warning_number + 1;
                            }

                            if ($room->config['other']['disconnecting_countdown'] != -1) {
                                $player->disconnecting_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['other']['disconnecting_countdown'] . ' seconds', strtotime($now)));
                            }

                            $player->save();

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }
                        }

                        if ($room->config['other']['warning_number'] != -1 && $player->warning_number > $room->config['other']['warning_number']) {

                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
                            $player->is_catching = false;
                            $player->is_caughting = false;
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

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }

                            $playerLeft = true;
                        }

                        if ($player->disconnecting_finished_at && $now > $player->disconnecting_finished_at || $player->crossing_boundary_finished_at && $now > $player->crossing_boundary_finished_at) {

                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
                            $player->is_catching = false;
                            $player->is_caughting = false;
                            $player->voting_answer = null;
                            $player->status = 'LEFT';
                            $player->failed_voting_type = null;
                            $player->warning_number = $room->config['other']['warning_number'] + 1;
                            $player->black_ticket_finished_at = null;
                            $player->fake_position_finished_at = null;
                            $player->disconnecting_finished_at = null;
                            $player->crossing_boundary_finished_at = null;
                            $player->speed_exceeded_at = null;
                            $player->next_voting_starts_at = null;
                            $player->save();

                            if ($player->user_id == $room->host_id) {
                                Other::setNewHost($room);
                            }

                            $playerLeft = true;
                        }

                        if (!$playerLeft) {
                            $activePlayersNumber++;
                        }

                    } else if ($player->status == 'SUPERVISING') {
                        $activePlayersNumber++;
                    }
                }

                if ($activePlayersNumber == 0) {

                    $tempConfig = $room->config;

                    if ($now < $room->game_ended_at) {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'] + strtotime($now) - strtotime($room->game_ended_at);
                    } else {
                        $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                    }

                    $room->config = $tempConfig;
                    $room->reporting_user_id = null;
                    $room->status = 'GAME_OVER';
                    $room->game_result = 'UNFINISHED';
                    $room->voting_type = null;
                    $room->game_ended_at = $now;
                    $room->next_disclosure_at = null;
                    $room->voting_ended_at = null;
                    $room->save();

                    /** @var Player[] $players */
                    $players = $room->players()->get();

                    foreach ($players as $player) {
                        $player->global_position = null;
                        $player->hidden_position = null;
                        $player->fake_position = null;
                        $player->is_catching = false;
                        $player->is_caughting = false;
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

                    break;
                }
            }

            $room = $room->fresh();

            if ($room->status == 'GAME_IN_PROGRESS' && $room->config['other']['is_pause_after_disconnecting']) {

                /** @var Player[] $allPlayers */
                $allPlayers = $room->players()->where('is_bot', false)->whereIn('status', ['CONNECTED', 'SUPERVISING'])->get();

                if (count($allPlayers) != $allPlayersNumber) {

                    $tempConfig = $room->config;
                    $tempConfig['duration']['real'] = strtotime($now) - strtotime($room->game_started_at);
                    $room->config = $tempConfig;

                    $room->status = 'GAME_PAUSED';
                    $room->save();

                    shell_exec('php ' . env('APP_ROOT') . "artisan room:check $room->id >/dev/null 2>/dev/null &");

                    break;
                }
            }

        } while ($room->status == 'GAME_IN_PROGRESS');

        return 0;
    }
}

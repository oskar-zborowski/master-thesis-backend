<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Http\Libraries\JsonConfig;
use App\Http\Libraries\Log;
use App\Http\Libraries\Other;
use App\Http\Libraries\Validation;
use App\Models\Connection;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class CheckVoting extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'voting:check {roomId} {userId}';

    /**
     * The console command description.
     */
    protected $description = 'Check the voting';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');
        $userId = $this->argument('userId');

        /** @var Player[] $players */
        $players = Player::where([
            'room_id' => $roomId,
            'is_bot' => false,
            'status' => 'CONNECTED',
        ])->get();

        $playersNumber = count($players);

        do {

            sleep(env('VOTING_CHECK_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if ($userId != $room->reporting_user_id) {

                /** @var Player $reportingUser */
                $reportingUser = $room->players()->where('user_id', $userId)->first();
                $reportingUser->voting_answer = null;
                $reportingUser->next_voting_starts_at = null;
                $reportingUser->save();

                break;
            }

            /** @var Player[] $players */
            $players = $room->players()->where([
                'is_bot' => false,
                'status' => 'CONNECTED',
            ])->get();

            if ($playersNumber != count($players)) {

                /** @var Player $reportingUser */
                $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                $reportingUser->next_voting_starts_at = null;
                $reportingUser->save();

                foreach ($players as $player) {
                    $player->voting_answer = null;
                    $player->failed_voting_type = $room->voting_type;
                    $player->save();
                }

                $room->reporting_user_id = null;
                $room->voting_type = null;
                $room->voting_ended_at = null;
                $room->save();

                break;
            }

            $votingEnd = false;
            $timeIsUp = false;

            if ($room->voting_ended_at && now() < $room->voting_ended_at) {

                $votersNumber = 0;

                /** @var Player[] $players */
                $players = $room->players()->where('is_bot', false)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

                foreach ($players as $player) {
                    if ($player->voting_answer !== null) {
                        $votersNumber++;
                    } else {
                        break;
                    }
                }

                if ($votersNumber == count($players)) {
                    $votingEnd = true;
                }

            } else {
                $votingEnd = true;
                $timeIsUp = true;
            }

            if ($votingEnd) {

                $successfulVote = false;
                $playersNumberFromCatchingFaction = 0;
                $playersNumberFromThievesFaction = 0;
                $playersNumberFromBothFaction = 0;
                $confirmationsNumberFromCatchingFaction = 0;
                $confirmationsNumberFromThievesFaction = 0;
                $confirmationsNumberFromBothFaction = 0;

                if ($timeIsUp) {
                    /** @var Player[] $players */
                    $players = $room->players()->where('is_bot', false)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
                }

                foreach ($players as $player) {

                    if ($room->voting_type != 'START' && $player->role == 'THIEF') {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromThievesFaction++;
                        }

                        $playersNumberFromThievesFaction++;

                    } else if ($room->voting_type != 'START' && $player->role !== null && $player->role != 'THIEF') {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromCatchingFaction++;
                        }

                        $playersNumberFromCatchingFaction++;

                    } else {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromBothFaction++;
                        }

                        $playersNumberFromBothFaction++;
                    }
                }

                if ($room->voting_type == 'START' &&
                    $confirmationsNumberFromBothFaction == $playersNumberFromBothFaction)
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if (in_array($room->voting_type, ['ENDING_COUNTDOWN', 'RESUME']) &&
                    $confirmationsNumberFromCatchingFaction == $playersNumberFromCatchingFaction && $confirmationsNumberFromThievesFaction == $playersNumberFromThievesFaction)
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if (in_array($room->voting_type, ['PAUSE', 'END_GAME']) &&
                    ($playersNumberFromCatchingFaction == 0 || $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5) &&
                    ($playersNumberFromThievesFaction == 0 || $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5))
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if ($room->voting_type == 'GIVE_UP') {

                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();

                    if ($reportingUser->role != 'THIEF' &&
                        ($playersNumberFromCatchingFaction == 0 || $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5))
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;

                    } else if ($reportingUser->role == 'THIEF' &&
                        ($playersNumberFromThievesFaction == 0 || $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5))
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;
                    }
                }

                if ($successfulVote) {

                    if ($room->voting_type == 'START') {

                        $allPlayers = 0;

                        foreach ($players as $player) {

                            if ($player->status == 'DISCONNECTED' && $player->voting_answer === null) {

                                $player->role = null;
                                $player->status = 'LEFT';
                                $player->save();

                            } else {
                                $allPlayers++;
                            }
                        }

                        $botNumber = $room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number'] - $allPlayers;

                        for ($i=0; $i<$botNumber; $i++) {
                            $player = new Player;
                            $player->room_id = $room->id;
                            $player->user_id = null;
                            $player->avatar = $this->findAvailableAvatar($room);
                            $player->is_bot = true;
                            $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('GAME_REFRESH') . ' seconds', strtotime(now())));
                            $player->save();
                        }

                        $this->setPlayersRoles($room);
                        $this->setPlayersConfig($room);

                        $room->status = 'GAME_IN_PROGRESS';
                        $room->game_started_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['escape_duration'] . ' seconds', strtotime(now())));
                        $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['duration']['scheduled'] . ' seconds', strtotime($room->game_started_at)));

                        if ($room->config['actor']['thief']['disclosure_interval'] != -1) {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($room->game_started_at)));
                        }

                        $gameStarted = true;

                        shell_exec('php ' . env('APP_ROOT') . "artisan game-course:check $room->id >/dev/null 2>/dev/null &");

                        if ($botNumber > 0) {
                            shell_exec('php ' . env('APP_ROOT') . "artisan thief-ai:start $room->id >/dev/null 2>/dev/null &");
                        }

                    } else if ($room->voting_type == 'ENDING_COUNTDOWN') {

                        $room->game_started_at = now();
                        $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['duration']['scheduled'] . ' seconds', strtotime($room->game_started_at)));

                        if ($room->config['actor']['thief']['disclosure_interval'] != -1) {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($room->game_started_at)));
                        }

                    } else if ($room->voting_type == 'PAUSE') {

                        $tempConfig = $room->config;
                        $tempConfig['duration']['real'] = strtotime(now()) - strtotime($room->game_started_at);
                        $room->config = $tempConfig;

                        $room->status = 'GAME_PAUSED';

                        shell_exec('php ' . env('APP_ROOT') . "artisan room:check $room->id >/dev/null 2>/dev/null &");

                    } else if ($room->voting_type == 'RESUME') {

                        $now = now();

                        $nextDisclosure = strtotime($room->game_ended_at) - strtotime($room->next_disclosure_at);

                        $room->status = 'GAME_IN_PROGRESS';

                        if ($room->config['duration']['real'] >= 0) {
                            $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . ($room->config['duration']['scheduled'] - $room->config['duration']['real']) . ' seconds', strtotime($now)));
                        } else {
                            $room->game_started_at = date('Y-m-d H:i:s', strtotime('+' . abs($room->config['duration']['real']) . ' seconds', strtotime($now)));
                            $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . ($room->config['duration']['scheduled'] + abs($room->config['duration']['real'])) . ' seconds', strtotime($now)));
                        }

                        if ($nextDisclosure > 0) {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('-' . $nextDisclosure . ' seconds', strtotime($room->game_ended_at)));
                        } else {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . abs($nextDisclosure) . ' seconds', strtotime($room->game_ended_at)));
                        }

                        /** @var Player $botExists */
                        $botExists = $room->players()->where('is_bot', true)->first();

                        shell_exec('php ' . env('APP_ROOT') . "artisan game-course:check $room->id >/dev/null 2>/dev/null &");

                        if ($botExists > 0) {
                            shell_exec('php ' . env('APP_ROOT') . "artisan thief-ai:start $room->id >/dev/null 2>/dev/null &");
                        }

                    } else if ($room->voting_type == 'END_GAME') {

                        $now = now();

                        $tempConfig = $room->config;

                        if ($now < $room->game_ended_at) {
                            $tempConfig['duration']['real'] = $room->config['duration']['scheduled'] + strtotime($now) - strtotime($room->game_ended_at);
                        } else {
                            $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                        }

                        $room->config = $tempConfig;
                        $room->status = 'GAME_OVER';
                        $room->game_result = 'DRAW';
                        $room->game_ended_at = $now;
                        $room->next_disclosure_at = null;

                        $gameEnded = true;

                    } else if ($room->voting_type == 'GIVE_UP') {

                        $now = now();

                        $tempConfig = $room->config;

                        if ($now < $room->game_ended_at) {
                            $tempConfig['duration']['real'] = $room->config['duration']['scheduled'] + strtotime($now) - strtotime($room->game_ended_at);
                        } else {
                            $tempConfig['duration']['real'] = $room->config['duration']['scheduled'];
                        }

                        $room->config = $tempConfig;
                        $room->status = 'GAME_OVER';

                        /** @var Player $reportingUser */
                        $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();

                        if ($reportingUser->role == 'THIEF') {
                            $room->game_result = 'THIEVES_SURRENDERED';
                        } else {
                            $room->game_result = 'POLICEMEN_SURRENDERED';
                        }

                        $room->game_ended_at = $now;
                        $room->next_disclosure_at = null;

                        $gameEnded = true;
                    }

                    if (isset($gameEnded)) {

                        /** @var Player[] $players */
                        $players = $room->players()->get();

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

                    } else {
                        foreach ($players as $player) {
                            $player->voting_answer = null;
                            $player->save();
                        }
                    }

                } else {
                    foreach ($players as $player) {
                        $player->voting_answer = null;
                        $player->failed_voting_type = $room->voting_type;
                        $player->save();
                    }
                }

                $room->reporting_user_id = null;
                $room->voting_type = null;
                $room->voting_ended_at = null;
                $room->save();

                if (isset($gameStarted)) {
                    $this->saveGpsLocation($room, $userId);
                }

                if (isset($gameEnded)) {
                    sleep(env('GAME_OVER_PAUSE'));
                    Other::createNewRoom($room);
                }
            }

        } while (!$votingEnd);

        return 0;
    }

    private function setPlayersRoles(Room $room) {

        $policemenNumber = 0;
        $thiefNumber = 0;
        $agentNumber = 0;
        $pegasusNumber = 0;
        $fattyManNumber = 0;
        $eagleNumber = 0;

        /** @var Player[] $players */
        $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        foreach ($players as $player) {
            if ($player->role == 'POLICEMAN') {
                $policemenNumber++;
            } else if ($player->role == 'THIEF') {
                $thiefNumber++;
            } else if ($player->role == 'AGENT') {
                $agentNumber++;
            } else if ($player->role == 'PEGASUS') {
                $pegasusNumber++;
            } else if ($player->role == 'FATTY_MAN') {
                $fattyManNumber++;
            } else if ($player->role == 'EAGLE') {
                $eagleNumber++;
            }
        }

        if ($room->config['actor']['agent']['probability'] == 1) {
            if ($room->config['actor']['agent']['number'] > $agentNumber) {
                $agentNumber = $room->config['actor']['agent']['number'] - $agentNumber;
            } else {
                $agentNumber = 0;
            }
        } else if ($room->config['actor']['agent']['probability'] == 0) {
            $agentNumber = 0;
        } else {
            $agentNumberRand = (int) ($room->config['actor']['agent']['number'] * rand((int) (200 * $room->config['actor']['agent']['probability']) - 100, 100) / 100);
            $agentNumber = $agentNumberRand > $agentNumber ? ($agentNumberRand - $agentNumber) : 0;
        }

        if ($room->config['actor']['pegasus']['probability'] == 1) {
            if ($room->config['actor']['pegasus']['number'] > $pegasusNumber) {
                $pegasusNumber = $room->config['actor']['pegasus']['number'] - $pegasusNumber;
            } else {
                $pegasusNumber = 0;
            }
        } else if ($room->config['actor']['pegasus']['probability'] == 0) {
            $pegasusNumber = 0;
        } else {
            $pegasusNumberRand = (int) ($room->config['actor']['pegasus']['number'] * rand((int) (200 * $room->config['actor']['pegasus']['probability']) - 100, 100) / 100);
            $pegasusNumber = $pegasusNumberRand > $pegasusNumber ? ($pegasusNumberRand - $pegasusNumber) : 0;
        }

        if ($room->config['actor']['fatty_man']['probability'] == 1) {
            if ($room->config['actor']['fatty_man']['number'] > $fattyManNumber) {
                $fattyManNumber = $room->config['actor']['fatty_man']['number'] - $fattyManNumber;
            } else {
                $fattyManNumber = 0;
            }
        } else if ($room->config['actor']['fatty_man']['probability'] == 0) {
            $fattyManNumber = 0;
        } else {
            $fattyManNumberRand = (int) ($room->config['actor']['fatty_man']['number'] * rand((int) (200 * $room->config['actor']['fatty_man']['probability']) - 100, 100) / 100);
            $fattyManNumber = $fattyManNumberRand > $fattyManNumber ? ($fattyManNumberRand - $fattyManNumber) : 0;
        }

        if ($room->config['actor']['eagle']['probability'] == 1) {
            if ($room->config['actor']['eagle']['number'] > $eagleNumber) {
                $eagleNumber = $room->config['actor']['eagle']['number'] - $eagleNumber;
            } else {
                $eagleNumber = 0;
            }
        } else if ($room->config['actor']['eagle']['probability'] == 0) {
            $eagleNumber = 0;
        } else {
            $eagleNumberRand = (int) ($room->config['actor']['eagle']['number'] * rand((int) (200 * $room->config['actor']['eagle']['probability']) - 100, 100) / 100);
            $eagleNumber = $eagleNumberRand > $eagleNumber ? ($eagleNumberRand - $eagleNumber) : 0;
        }

        if ($room->config['actor']['thief']['probability'] == 1) {
            if ($room->config['actor']['thief']['number'] > $thiefNumber) {
                $thiefNumber = $room->config['actor']['thief']['number'] - $thiefNumber;
            } else {
                $thiefNumber = 0;
            }
        } else if ($room->config['actor']['thief']['probability'] == 0) {
            if ($thiefNumber < 1) {
                $thiefNumber = 1;
            }
        } else {

            $thiefNumberRand = (int) ($room->config['actor']['thief']['number'] * rand((int) (200 * $room->config['actor']['thief']['probability']) - 100, 100) / 100);
            $thiefNumber2 = $thiefNumberRand > $thiefNumber ? ($thiefNumberRand - $thiefNumber) : 0;

            if ($thiefNumber2 < 1 && $thiefNumber < 1) {
                $thiefNumber = 1;
            } else if ($thiefNumber2 < 1 && $thiefNumber >= 1) {
                $thiefNumber = 0;
            } else if ($thiefNumber2 >= 1 && $thiefNumber < 1) {
                $thiefNumber = $thiefNumber2;
            } else {
                $thiefNumber = $thiefNumber2 - $thiefNumber;
            }
        }

        $thievesShouldBe = $room->config['actor']['thief']['number'];

        if ($thievesShouldBe < 1) {
            $thievesShouldBe = 1;
        }

        /** @var Player[] $players */
        $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereNull('role')->get();
        $countPlayers = count($players);

        $policemenNumber = $countPlayers - ($agentNumber + $pegasusNumber + $fattyManNumber + $eagleNumber + $thiefNumber);

        $roles = null;

        for ($i=0; $i<$policemenNumber; $i++) {
            $roles[] = 'POLICEMAN';
        }

        for ($i=0; $i<$thiefNumber; $i++) {
            $roles[] = 'THIEF';
        }

        for ($i=0; $i<$agentNumber; $i++) {
            $roles[] = 'AGENT';
        }

        for ($i=0; $i<$pegasusNumber; $i++) {
            $roles[] = 'PEGASUS';
        }

        for ($i=0; $i<$fattyManNumber; $i++) {
            $roles[] = 'FATTY_MAN';
        }

        for ($i=0; $i<$eagleNumber; $i++) {
            $roles[] = 'EAGLE';
        }

        shuffle($roles);

        $i = 0;

        foreach ($players as $player) {
            $player->role = $roles[$i++];
            $player->save();
        }
    }

    private function setPlayersConfig(Room $room) {

        /** @var Player[] $players */
        $players = $room->players()->whereIn('role', ['THIEF', 'PEGASUS'])->get();

        foreach ($players as $player) {

            if ($player->role == 'THIEF') {

                if ($room->config['actor']['thief']['black_ticket']['probability'] == 1) {
                    $blackTicketRand = $room->config['actor']['thief']['black_ticket']['number'];
                } else if ($room->config['actor']['thief']['black_ticket']['probability'] == 0) {
                    $blackTicketRand = 0;
                } else {
                    $blackTicketRand = (int) ($room->config['actor']['thief']['black_ticket']['number'] * rand((int) (200 * $room->config['actor']['thief']['black_ticket']['probability']) - 100, 100) / 100);
                    $blackTicketRand = $blackTicketRand >= 0 ? $blackTicketRand : 0;
                }

                if ($room->config['actor']['thief']['fake_position']['probability'] == 1) {
                    $fakePositionRand = $room->config['actor']['thief']['fake_position']['number'];
                } else if ($room->config['actor']['thief']['fake_position']['probability'] == 0) {
                    $fakePositionRand = 0;
                } else {
                    $fakePositionRand = (int) ($room->config['actor']['thief']['fake_position']['number'] * rand((int) (200 * $room->config['actor']['thief']['fake_position']['probability']) - 100, 100) / 100);
                    $fakePositionRand = $fakePositionRand >= 0 ? $fakePositionRand : 0;
                }

                $tempConfig = JsonConfig::getDefaultThiefConfig();
                $tempConfig['black_ticket']['number'] = $blackTicketRand;
                $tempConfig['fake_position']['number'] = $fakePositionRand;

                $player->config = $tempConfig;

            } else {

                if ($room->config['actor']['pegasus']['white_ticket']['probability'] == 1) {
                    $whiteTicketRand = $room->config['actor']['pegasus']['white_ticket']['number'];
                } else if ($room->config['actor']['pegasus']['white_ticket']['probability'] == 0) {
                    $whiteTicketRand = 0;
                } else {
                    $whiteTicketRand = (int) ($room->config['actor']['pegasus']['white_ticket']['number'] * rand((int) (200 * $room->config['actor']['pegasus']['white_ticket']['probability']) - 100, 100) / 100);
                    $whiteTicketRand = $whiteTicketRand >= 0 ? $whiteTicketRand : 0;
                }

                $tempConfig = JsonConfig::getDefaultPegasusConfig();
                $tempConfig['white_ticket']['number'] = $whiteTicketRand;

                $player->config = $tempConfig;
            }

            $player->save();
        }
    }

    private function findAvailableAvatar(Room $room) {

        $i = 0;
        $avatars = Validation::getAvatars();
        shuffle($avatars);

        do {

            $avatar = $avatars[$i++];

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where([
                'avatar' => $avatar,
                'status' => 'CONNECTED',
            ])->first();

        } while ($isAvatarExists);

        return $avatar;
    }

    private function saveGpsLocation(Room $room, int $userId) {

        $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);
        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
        $gpsLocation = substr($polygonCenter[0]->polygonCenter, 6, -1);
        $gpsLocation = explode(' ', $gpsLocation);

        $point['x'] = $gpsLocation[0];
        $point['y'] = $gpsLocation[1];

        $p = Geometry::convertXYToLatLng($point);

        $gpsLocation = "{$p['y']} {$p['x']}";

        /** @var Connection $connection */
        $connection = Connection::where('user_id', $userId)->orderBy('updated_at', 'desc')->first();

        /** @var \App\Models\IpAddress */
        $ipAddress = $connection->ipAddress()->first();

        $location = Log::getLocation($gpsLocation, $ipAddress->ip_address, $userId);

        $gpsLocation = explode(' ', $gpsLocation);
        $gpsLocation = "{$gpsLocation[1]} {$gpsLocation[0]}";

        $room->gps_location = $gpsLocation;

        if (isset($location['house_number'])) {
            $room->house_number = $location['house_number'];
        }

        if (isset($location['street'])) {
            $room->street = $location['street'];
        }

        if (isset($location['housing_estate'])) {
            $room->housing_estate = $location['housing_estate'];
        }

        if (isset($location['district'])) {
            $room->district = $location['district'];
        }

        if (isset($location['city'])) {
            $room->city = $location['city'];
        }

        if (isset($location['voivodeship'])) {
            $room->voivodeship = $location['voivodeship'];
        }

        if (isset($location['country'])) {
            $room->country = $location['country'];
        }

        $room->save();
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Libraries\Encrypter;
use App\Http\Libraries\JsonConfig;
use App\Http\Libraries\Log;
use App\Models\Connection;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
                $confirmationsNumberFromCatchingFaction = 0;
                $confirmationsNumberFromThievesFaction = 0;

                if ($timeIsUp) {
                    /** @var Player[] $players */
                    $players = $room->players()->where('is_bot', false)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
                }

                foreach ($players as $player) {

                    if ($player->role == 'THIEF') {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromThievesFaction++;
                        }

                        $playersNumberFromThievesFaction++;

                    } else {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromCatchingFaction++;
                        }

                        $playersNumberFromCatchingFaction++;
                    }
                }

                if (in_array($room->voting_type, ['START', 'ENDING_COUNTDOWN', 'RESUME']) &&
                    $confirmationsNumberFromCatchingFaction == $playersNumberFromCatchingFaction && $confirmationsNumberFromThievesFaction == $playersNumberFromThievesFaction)
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if (in_array($room->voting_type, ['PAUSE', 'END_GAME']) &&
                    $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5 && $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5)
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
                        $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5)
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;

                    } else if ($reportingUser->role == 'THIEF' &&
                        $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5)
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;
                    }
                }

                if ($successfulVote) {

                    foreach ($players as $player) {
                        $player->voting_answer = null;
                        $player->save();
                    }

                    if ($room->voting_type == 'START') {

                        $this->saveGpsLocation($room, $userId);
                        $this->setPlayersRoles($room);
                        $this->setPlayersConfig($room);

                        $room->status = 'GAME_IN_PROGRESS';
                        $room->game_started_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['escape_duration'] . ' seconds', strtotime(now())));
                        $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['duration']['scheduled'] . ' seconds', strtotime($room->game_started_at)));

                    } else if ($room->voting_type == 'ENDING_COUNTDOWN') {

                        $room->game_started_at = now();
                        $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['duration']['scheduled'] . ' seconds', strtotime($room->game_started_at)));
                        $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['disclosure_interval'] . ' seconds', strtotime($room->game_started_at)));

                    } else if ($room->voting_type == 'PAUSE') {

                        $room->config['duration']['real'] = strtotime(now()) - strtotime($room->game_started_at);
                        $room->status = 'GAME_PAUSED';

                    } else if ($room->voting_type == 'RESUME') {

                        $nextDisclosure = strtotime($room->game_ended_at) - strtotime($room->next_disclosure_at);

                        $room->status = 'GAME_IN_PROGRESS';
                        $room->game_ended_at = date('Y-m-d H:i:s', strtotime('+' . ($room->config['duration']['scheduled'] - $room->config['duration']['real']) . ' seconds', strtotime(now())));

                        if ($nextDisclosure > 0) {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('-' . $nextDisclosure . ' seconds', strtotime($room->game_ended_at)));
                        } else {
                            $room->next_disclosure_at = date('Y-m-d H:i:s', strtotime('+' . abs($nextDisclosure) . ' seconds', strtotime($room->game_ended_at)));
                        }

                    } else if ($room->voting_type == 'END_GAME') {

                        $room->status = 'GAME_OVER';
                        $room->game_result = 'DRAW';

                        $this->createNewRoom($room);

                        $room->boundary_polygon = null;

                        /** @var Player[] $players */
                        $players = $room->players()->get();

                        foreach ($players as $player) {
                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
                            $player->save();
                        }

                    } else if ($room->voting_type == 'GIVE_UP') {

                        $room->status = 'GAME_OVER';

                        /** @var Player $reportingUser */
                        $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();

                        if ($reportingUser->role == 'THIEF') {
                            $room->game_result = 'THIEVES_SURRENDERED';
                        } else {
                            $room->game_result = 'POLICEMEN_SURRENDERED';
                        }

                        $this->createNewRoom($room);

                        $room->boundary_polygon = null;

                        /** @var Player[] $players */
                        $players = $room->players()->get();

                        foreach ($players as $player) {
                            $player->global_position = null;
                            $player->hidden_position = null;
                            $player->fake_position = null;
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
            }

        } while (!$votingEnd);

        return 0;
    }

    private function saveGpsLocation(Room $room, int $userId) {

        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid($room->boundary_polygon)) AS polygonCenter"));
        $gpsLocation = substr($polygonCenter[0]->polygonCenter, 6, -1);

        /** @var Connection $connection */
        $connection = Connection::where('user_id', $userId)->orderBy('updated_at', 'desc')->first();

        /** @var \App\Models\IpAddress */
        $ipAddress = $connection->ipAddress()->first();

        $location = Log::getLocation($gpsLocation, $ipAddress->ip_address, $userId);

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

    private function setPlayersRoles(Room $room) {

        $agentNumber = $room->config['actor']['agent']['number'];
        $pegasusNumber = $room->config['actor']['pegasus']['number'];
        $fattyManNumber = $room->config['actor']['fatty_man']['number'];
        $eagleNumber = $room->config['actor']['eagle']['number'];
        $policemenNumber = $room->config['actor']['policeman']['number'] - ($agentNumber + $pegasusNumber + $fattyManNumber + $eagleNumber);
        $thiefNumber = $room->config['actor']['thief']['number'];

        /** @var Player[] $players */
        $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        foreach ($players as $player) {
            if ($player->role == 'POLICEMAN') {
                $policemenNumber--;
            } else if ($player->role == 'THIEF') {
                $thiefNumber--;
            } else if ($player->role == 'AGENT') {
                $agentNumber--;
            } else if ($player->role == 'PEGASUS') {
                $pegasusNumber--;
            } else if ($player->role == 'FATTY_MAN') {
                $fattyManNumber--;
            } else if ($player->role == 'EAGLE') {
                $eagleNumber--;
            }
        }

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
            if ($player->role === null) {
                $player->role = $roles[$i++];
                $player->save();
            }
        }
    }

    private function setPlayersConfig(Room $room) {

        /** @var Player[] $players */
        $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['THIEF', 'PEGASUS'])->get();

        foreach ($players as $player) {

            if ($player->role == 'THIEF') {

                $blackTicketRand = (int) ($room->config['actor']['thief']['black_ticket']['number'] * rand((int) (200 * $room->config['actor']['thief']['black_ticket']['probability']) - 100, 100) / 100);
                $blackTicketRand = $blackTicketRand >= 0 ? $blackTicketRand : 0;

                $fakePositionRand = (int) ($room->config['actor']['thief']['fake_position']['number'] * rand((int) (200 * $room->config['actor']['thief']['fake_position']['probability']) - 100, 100) / 100);
                $fakePositionRand = $fakePositionRand >= 0 ? $fakePositionRand : 0;

                $player->config = JsonConfig::getDefaultThiefConfig();
                $player->config['black_ticket']['number'] = $blackTicketRand;
                $player->config['fake_position']['number'] = $fakePositionRand;

            } else {

                $whiteTicketRand = (int) ($room->config['actor']['pegasus']['white_ticket']['number'] * rand((int) (200 * $room->config['actor']['pegasus']['white_ticket']['probability']) - 100, 100) / 100);
                $whiteTicketRand = $whiteTicketRand >= 0 ? $whiteTicketRand : 0;

                $player->config = JsonConfig::getDefaultPegasusConfig();
                $player->config['white_ticket']['number'] = $whiteTicketRand;
            }

            $player->save();
        }
    }

    private function createNewRoom(Room $room) {

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

        /** @var Player[] $players */
        $players = $room->players()->where('is_bot', false)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

        foreach ($players as $player) {
            $newPlayer = new Player;
            $newPlayer->room_id = $newRoom->id;
            $newPlayer->user_id = $player->user_id;
            $newPlayer->avatar = $player->avatar;
            $newPlayer->role = $player->role;
            $newPlayer->save();
        }
    }
}

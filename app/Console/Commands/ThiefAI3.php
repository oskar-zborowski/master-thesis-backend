<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Http\Libraries\ThiefAI as LibrariesThiefAi;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ThiefAI3 extends Command
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

        $microtime = microtime(true);

        $roomId = $this->argument('roomId');

        /** @var Room $room */
        $room = Room::where('id', $roomId)->first();

        if (!$room || $room->status != 'GAME_IN_PROGRESS') {
            die;
        }

        $isPermanentDisclosure = false;

        if ($room->config['actor']['policeman']['visibility_radius'] == -1) {
            $isPermanentDisclosure = true;
        } else {

            $area = LibrariesThiefAi::calcArea($room);

            $eagleMayExist = false;

            if ($room->config['actor']['eagle']['number'] > 0 && $room->config['actor']['eagle']['probability'] > 0) {
                $eagleMayExist = true;
            }

            $allAgents = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->where('role', 'AGENT')->get();
            $agentsNumber = count($allAgents);

            if ($eagleMayExist && $room->config['actor']['thief']['are_enemies_circles_visible']) {
                /** @var \App\Models\Player[] $allEagles */
                $allEagles = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->where('role', 'EAGLE')->get();
                $eaglesNumber = count($allEagles);
            } else {
                $eaglesNumber = round($room->config['actor']['eagle']['number'] * $room->config['actor']['eagle']['probability']);
            }

            /** @var \App\Models\Player[] $allPolicemenWithoutAgentsAndEagles */
            $allPolicemenWithoutAgentsAndEagles = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN'])->get();
            $allPolicemenWithoutAgentsAndEaglesNumber = count($allPolicemenWithoutAgentsAndEagles);

            $holeDisclosureArea = ($allPolicemenWithoutAgentsAndEaglesNumber + $agentsNumber) * pow($room->config['actor']['policeman']['visibility_radius'] + 2 * $room->config['other']['max_speed'] * env('BOT_REFRESH'), 2) * PI() + $eaglesNumber * pow($room->config['actor']['policeman']['visibility_radius'] * 2 + 2 * $room->config['other']['max_speed'] * env('BOT_REFRESH'), 2) * PI();

            if (2 * $holeDisclosureArea > $area) {
                $isPermanentDisclosure = true;
            }
        }

        $lastSavedTime = [];
        $lastDestinationLatLng = [];
        $lastDisclosureLatLng = [];
        $isDisclosure = [];

        /** @var \App\Models\Player[] $thieves */
        $thieves = $room->players()->where([
            'role' => 'THIEF',
            'is_bot' => true,
        ])->get();

        foreach ($thieves as $thief) {
            $lastSavedTime[$thief->id] = $microtime;
            $lastDestinationLatLng[$thief->id] = null;
            $lastDisclosureLatLng[$thief->id] = null;
            $isDisclosure[$thief->id] = $isPermanentDisclosure;
        }

        $spawnBotsStatus = LibrariesThiefAi::spawnBots($room);

        if (!$spawnBotsStatus) {
            LibrariesThiefAi::spawnBots($room, true);
        }

        $boundaryPointsXY = LibrariesThiefAi::getBoundaryPointsXY($room);
        $boundaryExtremePointsXY = LibrariesThiefAi::findExtremePointsXY($boundaryPointsXY);

        do {

            sleep(env('BOT_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if (!$room || $room->status != 'GAME_IN_PROGRESS') {
                break;
            }

            $timeLapse = (strtotime($room->game_ended_at) - strtotime(now())) / $room->config['duration']['scheduled'];

            if ($timeLapse > 1) {
                $timeLapse = 1;
            } else if ($timeLapse < 0) {
                $timeLapse = 0;
            }

            $timeLapse = 1 - $timeLapse;

            /** @var \App\Models\Player[] $thieves */
            $thieves = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            /** @var \App\Models\Player[] $policemen */
            $policemen = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['POLICEMAN', 'PEGASUS', 'EAGLE', 'FATTY_MAN'])->get();

            foreach ($thieves as $thief) {

                if ($thief->caught_at === null) {

                    $thief->mergeCasts([
                        'hidden_position' => Point::class,
                        'global_position' => Point::class,
                    ]);

                    $currentPositionLatLng = [
                        'x' => $thief->hidden_position->longitude,
                        'y' => $thief->hidden_position->latitude,
                    ];

                    $currentPositionXY = Geometry::convertLatLngToXY($currentPositionLatLng);

                    $randNewDestination = true;

                    if ($lastDestinationLatLng[$thief->id] !== null) {

                        $randNewDestination = false;

                        if ($thief->global_position !== null) {

                            $globalPositionLatLon['x'] = $thief->global_position->longitude;
                            $globalPositionLatLon['y'] = $thief->global_position->latitude;

                            if ($lastDisclosureLatLng[$thief->id] === null) {
                                $lastDisclosureLatLng[$thief->id] = $globalPositionLatLon;
                            }
                        }

                        if ($thief->global_position !== null && ($lastDisclosureLatLng[$thief->id]['x'] != $globalPositionLatLon['x'] || $lastDisclosureLatLng[$thief->id]['y'] != $globalPositionLatLon['y'])) {
                            $lastDisclosureLatLng[$thief->id] = $globalPositionLatLon;
                            $randNewDestination = true;
                        } else if (Geometry::getSphericalDistanceBetweenTwoPoints($currentPositionLatLng, $lastDestinationLatLng[$thief->id]) < 25) {
                            $randNewDestination = true;
                        } else {

                            $enemiesPosition = LibrariesThiefAi::checkEnemiesPosition($room, $policemen, $currentPositionLatLng, $lastDestinationLatLng[$thief->id], $isDisclosure[$thief->id]);

                            if ($enemiesPosition['randNewDestination']) {
                                $randNewDestination = true;
                            }
                        }
                    }

                    if ($randNewDestination) {

                        if (!$isPermanentDisclosure) {
                            $isDisclosure[$thief->id] = false;
                        }

                        $i = 0;

                        do {

                            if ($i > 0 && $i % 6 == 0) {
                                $isDisclosure[$thief->id] = true;
                            }

                            $newDestinationXY = LibrariesThiefAi::randLocationXY($boundaryExtremePointsXY);
                            $newDestinationLatLng = Geometry::convertXYToLatLng($newDestinationXY);

                            $enemiesPosition = LibrariesThiefAi::checkEnemiesPosition($room, $policemen, $currentPositionLatLng, $newDestinationLatLng, $isDisclosure[$thief->id]);

                            $isDisclosure[$thief->id] = $enemiesPosition['isDisclosure'];

                            $i++;

                        } while ($enemiesPosition['randNewDestination']);

                        $lastDestinationLatLng[$thief->id] = $newDestinationLatLng;
                    }

                    $lastDestinationXY = Geometry::convertLatLngToXY($lastDestinationLatLng[$thief->id]);

                    $botShift = $room->config['other']['bot_speed'] * (microtime(true) - $lastSavedTime[$thief->id]);
                    $finalPositionXY = $this->getShiftedPointXY($currentPositionXY, $lastDestinationXY, $botShift);
                    // $finalPositionXY = Geometry::getShiftedPoint($currentPositionXY, $lastDestinationXY, $botShift);
                    $finalPositionLatLng = Geometry::convertXYToLatLng($finalPositionXY);
                    $finalPositionLatLngString = "{$finalPositionLatLng['x']} {$finalPositionLatLng['y']}";

                    if (LibrariesThiefAi::checkToBeWithinXY($room, $finalPositionXY)) {

                        /** @var \App\Models\Player $appropriateThief */
                        $appropriateThief = $room->players()->where('id', $thief->id)->first();

                        $appropriateThief->hidden_position = DB::raw("ST_GeomFromText('POINT($finalPositionLatLngString)')");
                        $appropriateThief->save();

                    } else {
                        $lastDestinationLatLng[$thief->id] = null;
                    }

                    $lastSavedTime[$thief->id] = microtime(true);
                }
            }

            LibrariesThiefAi::useTicket($room, $timeLapse, $boundaryExtremePointsXY);

        } while ($room->status == 'GAME_IN_PROGRESS');
    }

    private function getShiftedPointXY(array $pointAXY, array $pointBXY, $targetDistance) {

        $currentDistance = Geometry::getSphericalDistanceBetweenTwoPoints(
            Geometry::convertXYToLatLng($pointAXY),
            Geometry::convertXYToLatLng($pointBXY)
        );

        if ($currentDistance > 0) {
            return ([
                'x' => $pointAXY['x'] + ($targetDistance * ($pointBXY['x'] - $pointAXY['x'])) / $currentDistance,
                'y' => $pointAXY['y'] + ($targetDistance * ($pointBXY['y'] - $pointAXY['y'])) / $currentDistance,
            ]);
        }

        return $pointAXY;
    }
}

<?php

namespace App\Http\Libraries;

use App\Models\Room;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Klasa obsługująca grę botów uciekających
 */
class ThiefAI
{
    public static function spawnBots(Room $room, bool $center = false) {

        $coordinatesXY = [];

        if (!$center) {

            /** @var \App\Models\Player[] $playersWithLocation */
            $playersWithLocation = $room->players()->whereNotNull('hidden_position')->get();

            if (count($playersWithLocation) > 0) {

                foreach ($playersWithLocation as $playerWithLocation) {

                    $playerWithLocation->mergeCasts([
                        'hidden_position' => Point::class,
                    ]);

                    $coordLatLng['x'] = $playerWithLocation->hidden_position->longitude;
                    $coordLatLng['y'] = $playerWithLocation->hidden_position->latitude;
                    $coordXY = Geometry::convertLatLngToXY($coordLatLng);

                    if (self::checkToBeWithinXY($room, $coordXY)) {
                        $coordinatesXY[] = $coordXY;
                    }
                }

                if (count($coordinatesXY) == 0) {
                    return false;
                }

            } else {
                return false;
            }

        } else {
            $mapCenterXY = self::findMapCenterXY($room);
            $coordinatesXY[] = $mapCenterXY;
        }

        /** @var \App\Models\Player[] $thieves */
        $thieves = $room->players()->where([
            'role' => 'THIEF',
            'is_bot' => true,
        ])->get();

        $area = self::calcArea($room);

        if ($area < 100) {
            $radius = sqrt($area) / 2;
        } else {
            $radius = 5;
        }

        foreach ($thieves as $thief) {

            $i = 0;

            do {

                if ($i > 0 && $i % 6 == 0) {

                    $radius /= 2;

                    if ($radius < 1) {
                        $radius = 0;
                    }
                }

                $spawnLocationXY = self::randSpawnLocationXY($coordinatesXY, $radius);

                $i++;

            } while (!self::checkToBeWithinXY($room, $spawnLocationXY));

            $spawnLocationLatLng = Geometry::convertXYToLatLng($spawnLocationXY);
            $spawnLocationLatLngString = "{$spawnLocationLatLng['x']} {$spawnLocationLatLng['y']}";

            $thief->hidden_position = DB::raw("ST_GeomFromText('POINT($spawnLocationLatLngString)')");
            $thief->save();
        }

        return true;
    }

    public static function getBoundaryPointsXY(Room $room) {

        $coordinatesXY = [];

        $boundaryLatLngString = $room->boundary_points;
        $boundaryXYString = Geometry::convertGeometryLatLngToXY($boundaryLatLngString);

        $boundaryXYArray = explode(',', $boundaryXYString);

        foreach ($boundaryXYArray as $singleBoundaryPointXYString) {

            $singleBoundaryPointXYArray = explode(' ', $singleBoundaryPointXYString);

            $singleBoundaryPointXY['x'] = $singleBoundaryPointXYArray[0];
            $singleBoundaryPointXY['y'] = $singleBoundaryPointXYArray[1];

            $coordinatesXY[] = $singleBoundaryPointXY;
        }

        return $coordinatesXY;
    }

    public static function findExtremePointsXY(array $coordinatesXY) {

        $n = null;
        $s = null;
        $e = null;
        $w = null;

        foreach ($coordinatesXY as $coordXY) {

            if ($n === null || $coordXY['y'] > $n) {
                $n = $coordXY['y'];
            }

            if ($s === null || $coordXY['y'] < $s) {
                $s = $coordXY['y'];
            }

            if ($e === null || $coordXY['x'] > $e) {
                $e = $coordXY['x'];
            }

            if ($w === null || $coordXY['x'] < $w) {
                $w = $coordXY['x'];
            }
        }

        return [
            'n' => $n,
            's' => $s,
            'e' => $e,
            'w' => $w,
        ];
    }

    public static function checkEnemiesPosition(Room $room, $policemen, array $currentPositionLatLng, array $lastDestinationLatLng, bool $isDisclosure) {

        $randNewDestination = false;

        $currentPositionXY = Geometry::convertLatLngToXY($currentPositionLatLng);
        $lastDestinationXY = Geometry::convertLatLngToXY($lastDestinationLatLng);

        foreach ($policemen as $policeman) {

            $policemanRadius = self::getPolicemanRadius($room->config, $policeman->role, $isDisclosure);
            $r = $policemanRadius['r'];
            $isDisclosure = $policemanRadius['isDisclosure'];

            $policeman->mergeCasts([
                'hidden_position' => Point::class,
            ]);

            // TODO Zamienić na global position
            if ($policeman->hidden_position !== null) {

                $policemanPositionLatLng['x'] = $policeman->hidden_position->longitude;
                $policemanPositionLatLng['y'] = $policeman->hidden_position->latitude;

                $policemanPositionXY = Geometry::convertLatLngToXY($policemanPositionLatLng);

                if (Geometry::checkIfPointBelongsToSegment2($policemanPositionXY, $currentPositionXY, $lastDestinationXY)) {

                    $intersectionPointAndLineXY = Geometry::findIntersectionPointAndLine($policemanPositionXY, $currentPositionXY, $lastDestinationXY);

                    if ($intersectionPointAndLineXY !== false) {

                        $intersectionPointAndLineLatLng = Geometry::convertXYToLatLng($intersectionPointAndLineXY);

                        if (Geometry::getSphericalDistanceBetweenTwoPoints($intersectionPointAndLineLatLng, $policemanPositionLatLng) <= $r + 2 * $room->config['other']['max_speed'] * env('BOT_REFRESH')) {
                            $randNewDestination = true;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'isDisclosure' => $isDisclosure,
            'randNewDestination' => $randNewDestination,
        ];
    }

    public static function randLocationXY(array $extremePointsXY) {

        $x = rand(round($extremePointsXY['w']), round($extremePointsXY['e']));
        $y = rand(round($extremePointsXY['s']), round($extremePointsXY['n']));

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    public static function calcArea(Room $room) {

        $boundaryLatLngString = $room->boundary_points;
        $boundaryXYString = Geometry::convertGeometryLatLngToXY($boundaryLatLngString);

        $area = DB::select(DB::raw("SELECT ST_Area(ST_GeomFromText('POLYGON(($boundaryXYString))')) AS area"));

        return $area[0]->area;
    }

    public static function checkToBeWithinXY(Room $room, array $locationXY) {

        $boundaryLatLngString = $room->boundary_points;
        $boundaryXYString = Geometry::convertGeometryLatLngToXY($boundaryLatLngString);

        $locationXYString = "{$locationXY['x']} {$locationXY['y']}";

        $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundaryXYString))'), ST_GeomFromText('POINT($locationXYString)')) AS isIntersects"));

        if ($isIntersects[0]->isIntersects) {
            return true;
        } else {
            return false;
        }
    }

    public static function useTicket(Room $room, float $timeLapse, array $boundaryExtremePointsXY) {

        $now = now();

        if ($now >= $room->game_started_at) {

            /** @var \App\Models\Player[] $thieves */
            $thieves = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            $remainingTime = strtotime($room->game_ended_at) - strtotime($now);

            if ($remainingTime < 0) {
                $remainingTime = 0;
            }

            foreach ($thieves as $thief) {

                if ($thief->caught_at === null) {

                    $thief->mergeCasts([
                        'hidden_position' => Point::class,
                        'global_position' => Point::class,
                    ]);

                    $sumAvailableTickets = ($thief->config['black_ticket']['number'] - $thief->config['black_ticket']['used_number']) * $room->config['actor']['thief']['black_ticket']['duration'] + ($thief->config['fake_position']['number'] - $thief->config['fake_position']['used_number']) * $room->config['actor']['thief']['fake_position']['duration'];

                    $useTicketCondition = false;

                    if ($sumAvailableTickets < $remainingTime) {

                        if ($room->config['actor']['thief']['black_ticket']['duration'] < $room->config['actor']['thief']['fake_position']['duration']) {
                            $blackOrFake = $room->config['actor']['thief']['black_ticket']['duration'];
                        } else {
                            $blackOrFake = $room->config['actor']['thief']['fake_position']['duration'];
                        }

                        $thiefPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                        $singlePolicemanVisibilityRadius = 1 * $room->config['actor']['policeman']['visibility_radius'];
                        $doublePolicemanVisibilityRadius = 2 * $room->config['actor']['policeman']['visibility_radius'];

                        if ($room->config['actor']['thief']['are_enemies_circles_visible']) {
                            $thiefVisibleByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $singlePolicemanVisibilityRadius"));
                            $thiefVisibleByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanVisibilityRadius"));
                        } else {
                            $thiefVisibleByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanVisibilityRadius"));
                            $thiefVisibleByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanVisibilityRadius"));
                        }

                        if (count($thiefVisibleByPoliceman) + count($thiefVisibleByEagle) > 0 || $room->config['actor']['policeman']['visibility_radius'] == -1) {
                            $useTicketCondition = true;
                        }

                        if ($useTicketCondition) {

                            $singlePolicemanCatchingRadius = 1 * $room->config['actor']['policeman']['catching']['radius'] + 4 * $room->config['other']['max_speed'] * env('BOT_REFRESH');
                            $doublePolicemanCatchingRadius = 2 * $room->config['actor']['policeman']['catching']['radius'] + 4 * $room->config['other']['max_speed'] * env('BOT_REFRESH');

                            if ($room->config['actor']['thief']['are_enemies_circles_visible']) {
                                $thiefCaughtByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $singlePolicemanCatchingRadius"));
                                $thiefCaughtByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanCatchingRadius"));
                            } else {
                                $thiefCaughtByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanCatchingRadius"));
                                $thiefCaughtByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefPosition)'), hidden_position) <= $doublePolicemanCatchingRadius"));
                            }

                            if ((rand(1, 30) == 30 && strtotime($room->next_disclosure_at) - strtotime($now) < $blackOrFake && strtotime($room->next_disclosure_at) - strtotime($now) > env('BOT_REFRESH')) ||
                                (count($thiefCaughtByPoliceman) + count($thiefCaughtByEagle) > 0))
                            {
                                $useTicketCondition = true;
                            } else {
                                $useTicketCondition = false;
                            }
                        }
                    }

                    if ($sumAvailableTickets >= $remainingTime || $useTicketCondition) {

                        if (($thief->fake_position_finished_at === null || $now > $thief->fake_position_finished_at) &&
                            ($thief->black_ticket_finished_at === null || $now > $thief->black_ticket_finished_at))
                        {
                            $blackTicketToUsed = 0;
                            $fakePositionToUsed = 0;

                            if ($thief->config['black_ticket']['number'] - $thief->config['black_ticket']['used_number'] > 0) {
                                $blackTicketToUsed = $thief->config['black_ticket']['number'] - $thief->config['black_ticket']['used_number'];
                            }

                            if ($thief->config['fake_position']['number'] - $thief->config['fake_position']['used_number'] > 0) {
                                $fakePositionToUsed = $thief->config['fake_position']['number'] - $thief->config['fake_position']['used_number'];
                            }

                            if ($blackTicketToUsed + $fakePositionToUsed > 0) {

                                $timeLapseRand = round(rand(round(200 * $timeLapse) - 100, 100) / 100);
                                $timeLapseRand = $timeLapseRand >= 0 ? $timeLapseRand : 0;

                                if ($timeLapseRand == 1 && ($sumAvailableTickets >= $remainingTime || rand(1, 20) == 20)) {

                                    $randTicket = rand(1, $blackTicketToUsed + $fakePositionToUsed);

                                    if ($blackTicketToUsed - $randTicket < 0) {

                                        do {

                                            if ($thief->global_position !== null) {
                                                $thiefPositionLatLng['x'] = $thief->global_position->longitude;
                                                $thiefPositionLatLng['y'] = $thief->global_position->latitude;
                                                $thiefPositionXY = Geometry::convertLatLngToXY($thiefPositionLatLng);
                                            }

                                            $destiantionXY = self::randLocationXY($boundaryExtremePointsXY);

                                            if ($thief->last_disclosure_at !== null && $thief->global_position !== null) {
                                                $botShift = $room->config['other']['bot_speed'] * (strtotime(now()) - strtotime($thief->last_disclosure_at));
                                                $finalPositionXY = Geometry::getShiftedPoint($thiefPositionXY, $destiantionXY, $botShift);
                                            } else {
                                                $finalPositionXY = $destiantionXY;
                                            }

                                            $finalPositionLatLng = Geometry::convertXYToLatLng($finalPositionXY);
                                            $finalPositionLatLngString = "{$finalPositionLatLng['x']} {$finalPositionLatLng['y']}";

                                            $singlePolicemanCatchingRadius2 = 1 * $room->config['actor']['policeman']['catching']['radius'];
                                            $doublePolicemanCatchingRadius2 = 2 * $room->config['actor']['policeman']['catching']['radius'];

                                            if ($room->config['actor']['thief']['are_enemies_circles_visible']) {
                                                $thiefCaughtByPoliceman2 = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($finalPositionLatLngString)'), hidden_position) <= $singlePolicemanCatchingRadius2"));
                                                $thiefCaughtByEagle2 = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($finalPositionLatLngString)'), hidden_position) <= $doublePolicemanCatchingRadius2"));
                                            } else {
                                                $thiefCaughtByPoliceman2 = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($finalPositionLatLngString)'), hidden_position) <= $doublePolicemanCatchingRadius2"));
                                                $thiefCaughtByEagle2 = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($finalPositionLatLngString)'), hidden_position) <= $doublePolicemanCatchingRadius2"));
                                            }

                                        } while (!self::checkToBeWithinXY($room, $finalPositionXY) || count($thiefCaughtByPoliceman2) + count($thiefCaughtByEagle2) > 0);

                                        $finalPositionLatLng = Geometry::convertXYToLatLng($finalPositionXY);
                                        $finalPositionLatLngString = "{$finalPositionLatLng['x']} {$finalPositionLatLng['y']}";

                                        $tempConfig = $thief->config;
                                        $tempConfig['fake_position']['used_number'] = $thief->config['fake_position']['used_number'] + 1;
                                        $thief->config = $tempConfig;

                                        $thief->fake_position = DB::raw("ST_GeomFromText('POINT($finalPositionLatLngString)')");
                                        $thief->fake_position_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['fake_position']['duration'] . ' seconds', strtotime($now)));
                                        $thief->save();

                                    } else {

                                        $tempConfig = $thief->config;
                                        $tempConfig['black_ticket']['used_number'] = $thief->config['black_ticket']['used_number'] + 1;
                                        $thief->config = $tempConfig;

                                        $thief->black_ticket_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['black_ticket']['duration'] . ' seconds', strtotime($now)));
                                        $thief->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private static function findMapCenterXY(Room $room) {

        $boundaryLatLngString = $room->boundary_points;
        $boundaryXYString = Geometry::convertGeometryLatLngToXY($boundaryLatLngString);
        $boundaryCenterXYQuery = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundaryXYString))'))) AS boundaryCenter"));
        $boundaryCenterXYString = substr($boundaryCenterXYQuery[0]->boundaryCenter, 6, -1);
        $boundaryCenterXYArray = explode(' ', $boundaryCenterXYString);

        $boundaryCenterXY['x'] = $boundaryCenterXYArray[0];
        $boundaryCenterXY['y'] = $boundaryCenterXYArray[1];

        return $boundaryCenterXY;
    }

    private static function randSpawnLocationXY(array $coordinatesXY, float $radius = 5) {

        $coordinatesNumber = count($coordinatesXY);
        $randCoord = rand(0, $coordinatesNumber-1);
        $randShiftX = rand(0, $radius);
        $randShiftXSign = rand(0, 1);
        $randShiftY = rand(0, $radius);
        $randShiftYSign = rand(0, 1);

        if ($randShiftXSign) {
            $randShiftX *= -1;
        }

        if ($randShiftYSign) {
            $randShiftY *= -1;
        }

        return [
            'x' => ($coordinatesXY[$randCoord]['x'] + $randShiftX),
            'y' => ($coordinatesXY[$randCoord]['y'] + $randShiftY),
        ];
    }

    private static function getPolicemanRadius(array $roomConfig, string $playerRole, bool $isDisclosure = false) {

        $eagleMayExist = false;

        if ($roomConfig['actor']['eagle']['number'] > 0 && $roomConfig['actor']['eagle']['probability'] > 0) {
            $eagleMayExist = true;
        }

        if ($roomConfig['actor']['policeman']['visibility_radius'] != -1 && !$isDisclosure) {

            if ($eagleMayExist && ($playerRole == 'EAGLE' || !$roomConfig['actor']['thief']['are_enemies_circles_visible'])) {
                $r = $roomConfig['actor']['policeman']['visibility_radius'] * 2;
            } else {
                $r = $roomConfig['actor']['policeman']['visibility_radius'];
            }

        } else {

            if ($eagleMayExist && ($playerRole == 'EAGLE' || !$roomConfig['actor']['thief']['are_enemies_circles_visible'])) {
                $r = $roomConfig['actor']['policeman']['catching']['radius'] * 2;
            } else {
                $r = $roomConfig['actor']['policeman']['catching']['radius'];
            }

            $isDisclosure = true;
        }

        return [
            'r' => $r,
            'isDisclosure' => $isDisclosure,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ThiefAi extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thief-ai:start {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Start the Thief AI';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        do {

            sleep(env('BOT_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            /** @var \App\Models\Player[] $thieves */
            $thieves = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            $isDisclosure = false;

            $visiblePolicemenByThieves = [];
            $destinations = [];
            $destinationsConfirmed = [];

            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                foreach ($thieves as $thief) {

                    if ($thief->hidden_position !== null) {

                        $thief->mergeCasts([
                            'hidden_position' => Point::class,
                        ]);

                        $thiefHiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";
                        $policemen = DB::select(DB::raw("SELECT id, role, ST_AsText(global_position) AS globalPosition FROM players WHERE room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']}"));

                        foreach ($policemen as $policeman) {

                            $policemanGlobalPosition = explode(' ', substr($policeman->globalPosition, 6, -1));

                            $visiblePolicemenByThieves[$thief->id][$policeman->id] = [
                                'role' => $policeman->role,
                                'longitude' => $policemanGlobalPosition[0],
                                'latitude' => $policemanGlobalPosition[1],
                            ];
                        }
                    }
                }

            } else {

                /** @var \App\Models\Player[] $policemen */
                $policemen = $room->players()->whereNotNull('global_position')->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE'])->get();

                foreach ($policemen as $policeman) {

                    $policeman->mergeCasts([
                        'global_position' => Point::class,
                    ]);

                    $visiblePolicemenByThieves['all'][$policeman->id] = [
                        'role' => $policeman->role,
                        'longitude' => $policeman->global_position->longitude,
                        'latitude' => $policeman->global_position->latitude,
                    ];
                }
            }

            do {

                if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                    foreach ($thieves as $thief) {

                        if (isset($visiblePolicemenByThieves[$thief->id])) {

                            $thief->mergeCasts([
                                'hidden_position' => Point::class,
                            ]);

                            $thiefHiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                            $policemenIdQuery = '(';

                            foreach ($visiblePolicemenByThieves[$thief->id] as $key => $value) {

                                $policemanGlobalPosition = "{$value['longitude']} {$value['latitude']}";

                                if ($value['role'] == 'EAGLE') {
                                    $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']} AND ((role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                                } else {
                                    $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']} AND ((role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                                }

                                if (count($nearestPolicemen) == 2) {

                                    $c1['x'] = $value['longitude'];
                                    $c1['y'] = $value['latitude'];

                                    $policemanRadius = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure);
                                    $isDisclosure = $policemanRadius['isDisclosure'];
                                    $c1['r'] = $policemanRadius['r'];

                                    $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                    $c2['x'] = $circle2[0];
                                    $c2['y'] = $circle2[1];
                                    $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role, $isDisclosure)['r'];

                                    $circle3 = explode(' ', substr($nearestPolicemen[1]->globalPosition, 6, -1));
                                    $c3['x'] = $circle3[0];
                                    $c3['y'] = $circle3[1];
                                    $c3['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[1]->role, $isDisclosure)['r'];

                                    $equidistantPoint = Geometry::findEquidistantPoint($c1, $c2, $c3);

                                    if ($isDisclosure) {
                                        $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                    } else {
                                        $disclosureDistanceCoefficient = 1;
                                    }

                                    if ($equidistantPoint) {
                                        if (!$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) {
                                            $destinations[$thief->id][] = [
                                                'x' => $equidistantPoint['x'],
                                                'y' => $equidistantPoint['y'],
                                                'r' => $equidistantPoint['r'],
                                                'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                            ];
                                        }
                                    }

                                } else if (count($nearestPolicemen) == 1) {

                                    $c1['x'] = $value['longitude'];
                                    $c1['y'] = $value['latitude'];
                                    $c1['r'] = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure)['r'];

                                    $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                    $c2['x'] = $circle2[0];
                                    $c2['y'] = $circle2[1];
                                    $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role, $isDisclosure)['r'];

                                    $equidistantPoint = Geometry::findSegmentMiddle($c1, $c2, true);

                                    if ($isDisclosure) {
                                        $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                    } else {
                                        $disclosureDistanceCoefficient = 1;
                                    }

                                    if (!$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) {
                                        $destinations[$thief->id][] = [
                                            'x' => $equidistantPoint['x'],
                                            'y' => $equidistantPoint['y'],
                                            'r' => $equidistantPoint['r'],
                                            'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                        ];
                                    }
                                }

                                $policemenIdQuery .= "id = $key OR ";
                            }

                            if ($policemenIdQuery != '(') {

                                $policemenIdQuery = substr($policemenIdQuery, 0, -4);
                                $policemenIdQuery .= ')';

                                $boundaryPoints = explode(',', $room->boundary_points);

                                foreach ($boundaryPoints as $boundaryPoint) {

                                    $nearestPoliceman = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));

                                    $bP = explode(' ', $boundaryPoint);
                                    $c1['x'] = $bP[0];
                                    $c1['y'] = $bP[1];

                                    $circle2 = explode(' ', substr($nearestPoliceman[0]->globalPosition, 6, -1));
                                    $c2['x'] = $circle2[0];
                                    $c2['y'] = $circle2[1];
                                    $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPoliceman[0]->role, $isDisclosure)['r'];

                                    if ($isDisclosure) {
                                        $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                    } else {
                                        $disclosureDistanceCoefficient = 1;
                                    }

                                    $destinations[$thief->id][] = [
                                        'x' => $c1['x'],
                                        'y' => $c1['y'],
                                        'r' => Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'],
                                        'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                    ];
                                }
                            }
                        }
                    }

                } else {

                    if (isset($visiblePolicemenByThieves['all'])) {

                        $policemenIdQuery = '(';

                        foreach ($visiblePolicemenByThieves['all'] as $key => $value) {

                            $policemanGlobalPosition = "{$value['longitude']} {$value['latitude']}";

                            if ($value['role'] == 'EAGLE') {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ((role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                            } else {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ((role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                            }

                            if (count($nearestPolicemen) == 2) {

                                $c1['x'] = $value['longitude'];
                                $c1['y'] = $value['latitude'];
                                $c1['r'] = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure)['r'];

                                $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                $c2['x'] = $circle2[0];
                                $c2['y'] = $circle2[1];
                                $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role, $isDisclosure)['r'];

                                $circle3 = explode(' ', substr($nearestPolicemen[1]->globalPosition, 6, -1));
                                $c3['x'] = $circle3[0];
                                $c3['y'] = $circle3[1];
                                $c3['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[1]->role, $isDisclosure)['r'];

                                $equidistantPoint = Geometry::findEquidistantPoint($c1, $c2, $c3);

                                if ($isDisclosure) {
                                    $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                } else {
                                    $disclosureDistanceCoefficient = 1;
                                }

                                if ($equidistantPoint) {
                                    if (!$this->checkPointRepetition($destinations['all'], $equidistantPoint)) {
                                        $destinations['all'][] = [
                                            'x' => $equidistantPoint['x'],
                                            'y' => $equidistantPoint['y'],
                                            'r' => $equidistantPoint['r'],
                                            'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                        ];
                                    }
                                }

                            } else if (count($nearestPolicemen) == 1) {

                                $c1['x'] = $value['longitude'];
                                $c1['y'] = $value['latitude'];
                                $c1['r'] = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure)['r'];

                                $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                $c2['x'] = $circle2[0];
                                $c2['y'] = $circle2[1];
                                $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role, $isDisclosure)['r'];

                                $equidistantPoint = Geometry::findSegmentMiddle($c1, $c2, true);

                                if ($isDisclosure) {
                                    $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                } else {
                                    $disclosureDistanceCoefficient = 1;
                                }

                                if (!$this->checkPointRepetition($destinations['all'], $equidistantPoint)) {
                                    $destinations['all'][] = [
                                        'x' => $equidistantPoint['x'],
                                        'y' => $equidistantPoint['y'],
                                        'r' => $equidistantPoint['r'],
                                        'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                    ];
                                }
                            }

                            $policemenIdQuery .= "id = $key OR ";
                        }

                        if ($policemenIdQuery != '(') {

                            $policemenIdQuery = substr($policemenIdQuery, 0, -4);
                            $policemenIdQuery .= ')';

                            $boundaryPoints = explode(',', $room->boundary_points);

                            foreach ($boundaryPoints as $boundaryPoint) {

                                $nearestPoliceman = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));

                                $bP = explode(' ', $boundaryPoint);
                                $c1['x'] = $bP[0];
                                $c1['y'] = $bP[1];

                                $circle2 = explode(' ', substr($nearestPoliceman[0]->globalPosition, 6, -1));
                                $c2['x'] = $circle2[0];
                                $c2['y'] = $circle2[1];
                                $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPoliceman[0]->role, $isDisclosure)['r'];

                                if ($isDisclosure) {
                                    $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                } else {
                                    $disclosureDistanceCoefficient = 1;
                                }

                                $destinations['all'][] = [
                                    'x' => $c1['x'],
                                    'y' => $c1['y'],
                                    'r' => Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'],
                                    'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                ];
                            }
                        }
                    }
                }

            } while (!$isDisclosure);

            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                foreach ($thieves as $thief) {

                    if (isset($destinations[$thief->id])) {

                        foreach ($destinations[$thief->id] as $destination) {

                            $break = false;

                            $c1['x'] = $destination['x'];
                            $c1['y'] = $destination['y'];
                            $c1['r'] = $destination['r'];

                            $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);

                            $point = Geometry::convertLatLngToXY($c1);

                            $p = "{$point['x']} {$point['y']}";

                            $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($p)')) AS isIntersects"));

                            if ($isIntersects[0]->isIntersects) {

                                foreach ($visiblePolicemenByThieves as $visiblePolicemenByThief) {

                                    foreach ($visiblePolicemenByThief as $visiblePolicemanByThief) {

                                        $c2['x'] = $visiblePolicemanByThief['longitude'];
                                        $c2['y'] = $visiblePolicemanByThief['latitude'];

                                        $distance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2);

                                        if ($distance < $c1['r']) {
                                            $break = true;
                                            break;
                                        }
                                    }

                                    if ($break) {
                                        break;
                                    }
                                }
                            }

                            if (!$break && $isIntersects[0]->isIntersects) {

                                $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);
                                $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
                                $polygonCenter = substr($polygonCenter[0]->polygonCenter, 6, -1);

                                $point = explode(' ', $polygonCenter);

                                $p1['x'] = $point[0];
                                $p1['y'] = $point[1];

                                $p2['x'] = $destination['x'];
                                $p2['y'] = $destination['y'];

                                $p2 = Geometry::convertLatLngToXY($p2);

                                $lastPoint = null;
                                $minDistance = null;
                                $minDistancePoint = null;

                                $boundaryPoints = explode(',', $boundary);

                                foreach ($boundaryPoints as $boundaryPoint) {

                                    $boundaryPoint = explode(' ', $boundaryPoint);

                                    $p3['x'] = $boundaryPoint[0];
                                    $p3['y'] = $boundaryPoint[1];

                                    if ($lastPoint !== null) {

                                        $linesIntersection = Geometry::findLinesIntersection($p1, $p2, $p3, $lastPoint);

                                        if ($linesIntersection) {

                                            $linesIntersection = Geometry::convertXYToLatLng($linesIntersection);

                                            $pDestination['x'] = $destination['x'];
                                            $pDestination['y'] = $destination['y'];

                                            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($pDestination, $linesIntersection);

                                            if ($minDistance === null || $distance < $minDistance) {
                                                $minDistance = $distance;
                                                $minDistancePoint = $linesIntersection;
                                            }
                                        }
                                    }

                                    $lastPoint['x'] = $boundaryPoint[0];
                                    $lastPoint['y'] = $boundaryPoint[1];
                                }

                                $centerLatLon = Geometry::convertXYToLatLng($p1);
                                $centerToBoundaryDistance = Geometry::getSphericalDistanceBetweenTwoPoints($centerLatLon, $minDistancePoint);

                                $distanceToCenterCoefficient = $minDistance / $centerToBoundaryDistance * 0.625 + 0.375;

                                $destinationsConfirmed[$thief->id][] = [
                                    'x' => $destination['x'],
                                    'y' => $destination['y'],
                                    'r' => $destination['r'],
                                    'disclosureDistanceCoefficient' => $destination['disclosureDistanceCoefficient'],
                                    'distanceToCenterCoefficient' => $distanceToCenterCoefficient,
                                ];
                            }
                        }
                    }
                }

            } else {

                if (isset($destinations['all'])) {

                    foreach ($destinations['all'] as $destination) {

                        $break = false;

                        $c1['x'] = $destination['x'];
                        $c1['y'] = $destination['y'];
                        $c1['r'] = $destination['r'];

                        $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);

                        $point = Geometry::convertLatLngToXY($c1);

                        $p = "{$point['x']} {$point['y']}";

                        $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($p)')) AS isIntersects"));

                        if ($isIntersects[0]->isIntersects) {

                            if (isset($visiblePolicemenByThieves['all'])) {

                                foreach ($visiblePolicemenByThieves['all'] as $visiblePolicemanByThief) {

                                    $c2['x'] = $visiblePolicemanByThief['longitude'];
                                    $c2['y'] = $visiblePolicemanByThief['latitude'];

                                    $distance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2);

                                    if ($distance < $c1['r']) {
                                        $break = true;
                                        break;
                                    }
                                }

                                if ($break) {
                                    break;
                                }
                            }
                        }

                        if (!$break && $isIntersects[0]->isIntersects) {

                            $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);
                            $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
                            $polygonCenter = substr($polygonCenter[0]->polygonCenter, 6, -1);

                            $point = explode(' ', $polygonCenter);

                            $p1['x'] = $point[0];
                            $p1['y'] = $point[1];

                            $p2['x'] = $destination['x'];
                            $p2['y'] = $destination['y'];

                            $p2 = Geometry::convertLatLngToXY($p2);

                            $lastPoint = null;
                            $minDistance = null;
                            $minDistancePoint = null;

                            $boundaryPoints = explode(',', $boundary);

                            foreach ($boundaryPoints as $boundaryPoint) {

                                $boundaryPoint = explode(' ', $boundaryPoint);

                                $p3['x'] = $boundaryPoint[0];
                                $p3['y'] = $boundaryPoint[1];

                                if ($lastPoint !== null) {

                                    $linesIntersection = Geometry::findLinesIntersection($p1, $p2, $p3, $lastPoint);

                                    if ($linesIntersection) {

                                        $linesIntersection = Geometry::convertXYToLatLng($linesIntersection);

                                        $pDestination['x'] = $destination['x'];
                                        $pDestination['y'] = $destination['y'];

                                        $distance = Geometry::getSphericalDistanceBetweenTwoPoints($pDestination, $linesIntersection);

                                        if ($minDistance === null || $distance < $minDistance) {
                                            $minDistance = $distance;
                                            $minDistancePoint = $linesIntersection;
                                        }
                                    }
                                }

                                $lastPoint['x'] = $boundaryPoint[0];
                                $lastPoint['y'] = $boundaryPoint[1];
                            }

                            $centerLatLon = Geometry::convertXYToLatLng($p1);
                            $centerToBoundaryDistance = Geometry::getSphericalDistanceBetweenTwoPoints($centerLatLon, $minDistancePoint);

                            $distanceToCenterCoefficient = $minDistance / $centerToBoundaryDistance * 0.625 + 0.375;

                            $destinationsConfirmed['all'][] = [
                                'x' => $destination['x'],
                                'y' => $destination['y'],
                                'r' => $destination['r'],
                                'disclosureDistanceCoefficient' => $destination['disclosureDistanceCoefficient'],
                                'distanceToCenterCoefficient' => $distanceToCenterCoefficient,
                            ];
                        }
                    }
                }
            }

        } while (false);
    }

    private function getPolicemanRadius(array $roomConfig, string $playerRole, bool $isDisclosure = false) {

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

    private function checkPointRepetition(array $destinations, array $equidistantPoint) {

        $equidistantPointExists = false;

        foreach ($destinations as $destination) {
            if ($destination['x'] != $equidistantPoint['x'] || $destination['y'] != $equidistantPoint['y'] || $destination['r'] != $equidistantPoint['r']) {
                $equidistantPointExists = true;
                break;
            }
        }

        return $equidistantPointExists;
    }
}

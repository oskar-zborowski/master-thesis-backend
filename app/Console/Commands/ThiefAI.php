<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ThiefAI extends Command
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

        $globalCounter = 0;

        $roomId = $this->argument('roomId');

        do {

            sleep(env('BOT_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if ($room) {
                /** @var \App\Models\Player[] $thieves */
                $thieves = $room->players()->where([
                    'role' => 'THIEF',
                    'is_bot' => true,
                ])->get();
            } else {
                break;
            }

            /** @var \App\Models\Player[] $thievesWithoutLocation */
            $thievesWithoutLocation = $room->players()->whereNull('hidden_position')->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            if (count($thievesWithoutLocation) > 0) {

                $allLocation = [];
                $allLocationNumber = 0;

                /** @var \App\Models\Player[] $allPlayers */
                $allPlayers = $room->players()->whereNotNull('hidden_position')->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

                foreach ($allPlayers as $aP) {

                    $aP->mergeCasts([
                        'hidden_position' => Point::class,
                    ]);

                    $allLocation[] = "{$aP->hidden_position->longitude} {$aP->hidden_position->latitude}";
                }

                $allLocationNumber = count($allLocation);

                if ($allLocationNumber > 0) {
                    foreach ($thievesWithoutLocation as $tWL) {
                        $rand = rand(0, $allLocationNumber-1);
                        $botPos = $allLocation[$rand];
                        $tWL->hidden_position = DB::raw("ST_GeomFromText('POINT($botPos)')");;
                        $tWL->save();
                    }
                }
            }

            $isDisclosure = false;
            $iteration = 0;

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
                        $singleThiefVisibilityRadius = 1 * $room->config['actor']['thief']['visibility_radius'];
                        $policemen = DB::select(DB::raw("SELECT id, role, ST_AsText(global_position) AS globalPosition FROM players WHERE room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= $singleThiefVisibilityRadius"));

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

                if ($iteration == 1) {
                    $isDisclosure = true;
                }

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

                                $singlePolicemanVisibilityRadius = 1 * $room->config['actor']['policeman']['visibility_radius'];
                                $singleThiefVisibilityRadius = 1 * $room->config['actor']['thief']['visibility_radius'];

                                if ($value['role'] == 'EAGLE') {
                                    $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= $singleThiefVisibilityRadius AND ((role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > $singlePolicemanVisibilityRadius) OR (role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                                } else {
                                    $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND global_position IS NOT NULL AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= $singleThiefVisibilityRadius AND ((role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > $singlePolicemanVisibilityRadius) OR (role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
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
                                        if ((!isset($destinations[$thief->id]) || !$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) && $equidistantPoint['r'] >= 0) {
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

                                    $policemanRadius = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure);
                                    $isDisclosure = $policemanRadius['isDisclosure'];
                                    $c1['r'] = $policemanRadius['r'];

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

                                    if ((!isset($destinations[$thief->id]) || !$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) && $equidistantPoint['r'] >= 0) {
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

                                $secondIterator = 0;

                                foreach ($boundaryPoints as $boundaryPoint) {

                                    if ($secondIterator > 0) {

                                        $nearestPoliceman = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE role <> 'EAGLE' AND $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));
                                        $nearestEagle = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE role = 'EAGLE' AND $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));

                                        $bP = explode(' ', $boundaryPoint);
                                        $c1['x'] = $bP[0];
                                        $c1['y'] = $bP[1];

                                        if (isset($nearestPoliceman[0])) {

                                            $circle2 = explode(' ', substr($nearestPoliceman[0]->globalPosition, 6, -1));
                                            $c2['x'] = $circle2[0];
                                            $c2['y'] = $circle2[1];

                                            $policemanRadius = $this->getPolicemanRadius($room->config, $nearestPoliceman[0]->role, $isDisclosure);
                                            $isDisclosure = $policemanRadius['isDisclosure'];
                                            $c2['r'] = $policemanRadius['r'];
                                        }

                                        if (isset($nearestEagle[0])) {

                                            $circle3 = explode(' ', substr($nearestEagle[0]->globalPosition, 6, -1));
                                            $c3['x'] = $circle3[0];
                                            $c3['y'] = $circle3[1];

                                            $policemanRadius = $this->getPolicemanRadius($room->config, $nearestEagle[0]->role, $isDisclosure);
                                            $isDisclosure = $policemanRadius['isDisclosure'];
                                            $c3['r'] = $policemanRadius['r'];
                                        }

                                        if ($isDisclosure) {
                                            $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                        } else {
                                            $disclosureDistanceCoefficient = 1;
                                        }

                                        if (isset($c2) && isset($c3)) {

                                            $c2r = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'];
                                            $c3r = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c3) - $c3['r'];

                                            if ($c2r < $c3r) {
                                                $c1['r'] = $c2r;
                                            } else {
                                                $c1['r'] = $c3r;
                                            }

                                        } else if (isset($c2)) {
                                            $c1['r'] = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'];
                                        } else if (isset($c3)) {
                                            $c1['r'] = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c3) - $c3['r'];
                                        }

                                        if ((!isset($destinations[$thief->id]) || !$this->checkPointRepetition($destinations[$thief->id], $c1)) && isset($c1['r']) && $c1['r'] >= 0) {
                                            $destinations[$thief->id][] = [
                                                'x' => $c1['x'],
                                                'y' => $c1['y'],
                                                'r' => $c1['r'],
                                                'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                            ];
                                        }
                                    }

                                    $secondIterator++;
                                }
                            }
                        }
                    }

                } else {

                    if (isset($visiblePolicemenByThieves['all'])) {

                        $policemenIdQuery = '(';

                        foreach ($visiblePolicemenByThieves['all'] as $key => $value) {

                            $policemanGlobalPosition = "{$value['longitude']} {$value['latitude']}";

                            $singlePolicemanVisibilityRadius = 1 * $room->config['actor']['policeman']['visibility_radius'];

                            if ($value['role'] == 'EAGLE') {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ((role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > $singlePolicemanVisibilityRadius) OR (role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                            } else {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ((role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > $singlePolicemanVisibilityRadius) OR (role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
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
                                    if ((!isset($destinations['all']) || !$this->checkPointRepetition($destinations['all'], $equidistantPoint)) && $equidistantPoint['r'] >= 0) {
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

                                $policemanRadius = $this->getPolicemanRadius($room->config, $value['role'], $isDisclosure);
                                $isDisclosure = $policemanRadius['isDisclosure'];
                                $c1['r'] = $policemanRadius['r'];

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

                                if ((!isset($destinations['all']) || !$this->checkPointRepetition($destinations['all'], $equidistantPoint)) && $equidistantPoint['r'] >= 0) {
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

                            $secondIterator = 0;

                            foreach ($boundaryPoints as $boundaryPoint) {

                                if ($secondIterator > 0) {

                                    $nearestPoliceman = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE role <> 'EAGLE' AND $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));
                                    $nearestEagle = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE role = 'EAGLE' AND $policemenIdQuery ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));

                                    $bP = explode(' ', $boundaryPoint);
                                    $c1['x'] = $bP[0];
                                    $c1['y'] = $bP[1];

                                    if (isset($nearestPoliceman[0])) {

                                        $circle2 = explode(' ', substr($nearestPoliceman[0]->globalPosition, 6, -1));
                                        $c2['x'] = $circle2[0];
                                        $c2['y'] = $circle2[1];

                                        $policemanRadius = $this->getPolicemanRadius($room->config, $nearestPoliceman[0]->role, $isDisclosure);
                                        $isDisclosure = $policemanRadius['isDisclosure'];
                                        $c2['r'] = $policemanRadius['r'];
                                    }

                                    if (isset($nearestEagle[0])) {

                                        $circle3 = explode(' ', substr($nearestEagle[0]->globalPosition, 6, -1));
                                        $c3['x'] = $circle3[0];
                                        $c3['y'] = $circle3[1];

                                        $policemanRadius = $this->getPolicemanRadius($room->config, $nearestEagle[0]->role, $isDisclosure);
                                        $isDisclosure = $policemanRadius['isDisclosure'];
                                        $c3['r'] = $policemanRadius['r'];
                                    }

                                    if ($isDisclosure) {
                                        $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                    } else {
                                        $disclosureDistanceCoefficient = 1;
                                    }

                                    if (isset($c2) && isset($c3)) {

                                        $c2r = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'];
                                        $c3r = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c3) - $c3['r'];

                                        if ($c2r < $c3r) {
                                            $c1['r'] = $c2r;
                                        } else {
                                            $c1['r'] = $c3r;
                                        }

                                    } else if (isset($c2)) {
                                        $c1['r'] = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $c2['r'];
                                    } else if (isset($c3)) {
                                        $c1['r'] = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c3) - $c3['r'];
                                    }

                                    if ((!isset($destinations['all']) || !$this->checkPointRepetition($destinations['all'], $c1)) && isset($c1['r']) && $c1['r'] >= 0) {
                                        $destinations['all'][] = [
                                            'x' => $c1['x'],
                                            'y' => $c1['y'],
                                            'r' => $c1['r'],
                                            'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                        ];
                                    }
                                }

                                $secondIterator++;
                            }
                        }
                    }
                }

                $iteration++;

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

                                $eagleMayExist = false;

                                if ($room->config['actor']['eagle']['number'] > 0 && $room->config['actor']['eagle']['probability'] > 0) {
                                    $eagleMayExist = true;
                                }

                                $minVisibilityDistance = null;
                                $minDisclosureDistance = null;

                                foreach ($visiblePolicemenByThieves as $visiblePolicemenByThief) {

                                    foreach ($visiblePolicemenByThief as $visiblePolicemanByThief) {

                                        $c2['x'] = $visiblePolicemanByThief['longitude'];
                                        $c2['y'] = $visiblePolicemanByThief['latitude'];

                                        if ($eagleMayExist && ($visiblePolicemanByThief['role'] == 'EAGLE' || !$room->config['actor']['thief']['are_enemies_circles_visible'])) {
                                            $visibilityDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['visibility_radius'] * 2;
                                            $disclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['catching']['radius'] * 2;
                                        } else {
                                            $visibilityDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['visibility_radius'];
                                            $disclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['catching']['radius'];
                                        }

                                        if ($minVisibilityDistance === null || $visibilityDistance < $minVisibilityDistance) {
                                            $minVisibilityDistance = $visibilityDistance;
                                        }

                                        if ($minDisclosureDistance === null || $disclosureDistance < $minDisclosureDistance) {
                                            $minDisclosureDistance = $disclosureDistance;
                                        }
                                    }
                                }

                                if ($minDisclosureDistance < 0) {
                                    $break = true;
                                } else if ($minVisibilityDistance < 0) {
                                    $destinationDistance = $minDisclosureDistance;
                                    $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                } else {
                                    $destinationDistance = $minDisclosureDistance;
                                    $disclosureDistanceCoefficient = 1;
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

                                if ($centerToBoundaryDistance != 0) {
                                    $distanceToCenterCoefficient = $minDistance / $centerToBoundaryDistance * 0.625 + 0.375;
                                } else {
                                    $distanceToCenterCoefficient = 1;
                                }

                                if ($thief->global_position !== null) {

                                    $thief->mergeCasts([
                                        'global_position' => Point::class,
                                    ]);

                                    $thiefPos['x'] = $thief->global_position->longitude;
                                    $thiefPos['y'] = $thief->global_position->latitude;

                                    $lastDisclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($thiefPos, $destination);

                                } else {
                                    $lastDisclosureDistance = -1;
                                }

                                $destinationsConfirmed[$thief->id][] = [
                                    'x' => $destination['x'],
                                    'y' => $destination['y'],
                                    'r' => $destinationDistance,
                                    'lastDisclosureDistance' => $lastDisclosureDistance,
                                    'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                    'distanceToCenterCoefficient' => $distanceToCenterCoefficient,
                                ];
                            }
                        }

                        if (isset($destinationsConfirmed[$thief->id])) {

                            $maxDistance = null;
                            $maxLastDisclosureDistance = null;

                            foreach ($destinationsConfirmed[$thief->id] as $destinationConfirmed) {

                                if ($maxDistance === null || $destinationConfirmed['r'] > $maxDistance) {
                                    $maxDistance = $destinationConfirmed['r'];
                                }

                                if ($maxLastDisclosureDistance === null || $destinationConfirmed['lastDisclosureDistance'] > $maxLastDisclosureDistance) {
                                    $maxLastDisclosureDistance = $destinationConfirmed['lastDisclosureDistance'];
                                }
                            }

                            foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed) {

                                if ($maxDistance != 0) {
                                    $maxDistanceCoefficient = $destinationConfirmed['r'] / $maxDistance;
                                    $destinationConfirmed['maxDistanceCoefficient'] = $maxDistanceCoefficient;
                                } else {
                                    $destinationConfirmed['maxDistanceCoefficient'] = 1;
                                }

                                if ($destinationConfirmed['lastDisclosureDistance'] != -1) {

                                    if ($maxLastDisclosureDistance != 0) {
                                        $lastDisclosureDistanceCoefficient = $destinationConfirmed['lastDisclosureDistance'] / $maxLastDisclosureDistance;
                                        $destinationConfirmed['lastDisclosureDistanceCoefficient'] = $lastDisclosureDistanceCoefficient;
                                    } else {
                                        $destinationConfirmed['lastDisclosureDistanceCoefficient'] = 1;
                                    }

                                } else {
                                    $destinationConfirmed['lastDisclosureDistanceCoefficient'] = 1;
                                }
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

                                $eagleMayExist = false;

                                if ($room->config['actor']['eagle']['number'] > 0 && $room->config['actor']['eagle']['probability'] > 0) {
                                    $eagleMayExist = true;
                                }

                                $minVisibilityDistance = null;
                                $minDisclosureDistance = null;

                                foreach ($visiblePolicemenByThieves['all'] as $visiblePolicemanByThief) {

                                    $c2['x'] = $visiblePolicemanByThief['longitude'];
                                    $c2['y'] = $visiblePolicemanByThief['latitude'];

                                    if ($eagleMayExist && ($visiblePolicemanByThief['role'] == 'EAGLE' || !$room->config['actor']['thief']['are_enemies_circles_visible'])) {
                                        $visibilityDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['visibility_radius'] * 2;
                                        $disclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['catching']['radius'] * 2;
                                    } else {
                                        $visibilityDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['visibility_radius'];
                                        $disclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($c1, $c2) - $room->config['actor']['policeman']['catching']['radius'];
                                    }

                                    if ($minVisibilityDistance === null || $visibilityDistance < $minVisibilityDistance) {
                                        $minVisibilityDistance = $visibilityDistance;
                                    }

                                    if ($minDisclosureDistance === null || $disclosureDistance < $minDisclosureDistance) {
                                        $minDisclosureDistance = $disclosureDistance;
                                    }
                                }

                                if ($minDisclosureDistance < 0) {
                                    $break = true;
                                } else if ($minVisibilityDistance < 0) {
                                    $destinationDistance = $minDisclosureDistance;
                                    $disclosureDistanceCoefficient = env('BOT_THIEF_DISCLOSURE_DISTANCE_COEFFICIENT');
                                } else {
                                    $destinationDistance = $minDisclosureDistance;
                                    $disclosureDistanceCoefficient = 1;
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

                            if ($centerToBoundaryDistance != 0) {
                                $distanceToCenterCoefficient = $minDistance / $centerToBoundaryDistance * 0.625 + 0.375;
                            } else {
                                $distanceToCenterCoefficient = 1;
                            }

                            $destinationsConfirmed['all'][] = [
                                'x' => $destination['x'],
                                'y' => $destination['y'],
                                'r' => $destinationDistance,
                                'disclosureDistanceCoefficient' => $disclosureDistanceCoefficient,
                                'distanceToCenterCoefficient' => $distanceToCenterCoefficient,
                            ];
                        }
                    }

                    if (isset($destinationsConfirmed['all'])) {

                        $maxDistance = null;

                        foreach ($destinationsConfirmed['all'] as $destinationConfirmed) {
                            if ($maxDistance === null || $destinationConfirmed['r'] > $maxDistance) {
                                $maxDistance = $destinationConfirmed['r'];
                            }
                        }

                        foreach ($destinationsConfirmed['all'] as &$destinationConfirmed) {
                            if ($maxDistance != 0) {
                                $maxDistanceCoefficient = $destinationConfirmed['r'] / $maxDistance;
                                $destinationConfirmed['maxDistanceCoefficient'] = $maxDistanceCoefficient;
                            } else {
                                $destinationConfirmed['maxDistanceCoefficient'] = 1;
                            }
                        }
                    }
                }
            }

            if (isset($destinationsConfirmed['all'])) {

                $tempDestiantionsConfirmed = null;

                foreach ($thieves as $thief) {

                    foreach ($destinationsConfirmed['all'] as $destinationConfirmed3) {

                        if ($thief->global_position !== null) {

                            $thief->mergeCasts([
                                'global_position' => Point::class,
                            ]);

                            $thiefPos['x'] = $thief->global_position->longitude;
                            $thiefPos['y'] = $thief->global_position->latitude;

                            $lastDisclosureDistance = Geometry::getSphericalDistanceBetweenTwoPoints($thiefPos, $destinationConfirmed3);

                        } else {
                            $lastDisclosureDistance = -1;
                        }

                        $tempDestiantionsConfirmed[$thief->id][] = [
                            'x' => $destinationConfirmed3['x'],
                            'y' => $destinationConfirmed3['y'],
                            'r' => $destinationConfirmed3['r'],
                            'lastDisclosureDistance' => $lastDisclosureDistance,
                            'disclosureDistanceCoefficient' => $destinationConfirmed3['disclosureDistanceCoefficient'],
                            'distanceToCenterCoefficient' => $destinationConfirmed3['distanceToCenterCoefficient'],
                            'maxDistanceCoefficient' => $destinationConfirmed3['maxDistanceCoefficient'],
                        ];
                    }

                    $maxLastDisclosureDistance = null;

                    foreach ($tempDestiantionsConfirmed[$thief->id] as $destinationConfirmed4) {
                        if ($maxLastDisclosureDistance === null || $destinationConfirmed4['lastDisclosureDistance'] > $maxLastDisclosureDistance) {
                            $maxLastDisclosureDistance = $destinationConfirmed4['lastDisclosureDistance'];
                        }
                    }

                    foreach ($tempDestiantionsConfirmed[$thief->id] as &$destinationConfirmed5) {

                        if ($destinationConfirmed5['lastDisclosureDistance'] != -1) {

                            if ($maxLastDisclosureDistance != 0) {
                                $lastDisclosureDistanceCoefficient = $destinationConfirmed5['lastDisclosureDistance'] / $maxLastDisclosureDistance;
                                $destinationConfirmed5['lastDisclosureDistanceCoefficient'] = $lastDisclosureDistanceCoefficient;
                            } else {
                                $destinationConfirmed5['lastDisclosureDistanceCoefficient'] = 1;
                            }

                        } else {
                            $destinationConfirmed5['lastDisclosureDistanceCoefficient'] = 1;
                        }
                    }

                    $destinationsConfirmed2 = $destinationsConfirmed;
                    $destinationsConfirmed = null;
                    $destinationsConfirmed = $destinationsConfirmed2 + $tempDestiantionsConfirmed;
                }
            }

            $thiefDecision = null;

            $timeLapse = (strtotime($room->game_ended_at) - strtotime(now())) / $room->config['duration']['scheduled'];

            if ($timeLapse > 1) {
                $timeLapse = 1;
            } else if ($timeLapse < 0) {
                $timeLapse = 0;
            }

            $timeLapse = 1 - $timeLapse;

            foreach ($thieves as $thief) {

                if ($thief->hidden_position !== null) {

                    $thief->mergeCasts([
                        'hidden_position' => Point::class,
                    ]);

                    $thiefPos['x'] = $thief->hidden_position->longitude;
                    $thiefPos['y'] = $thief->hidden_position->latitude;

                    $thiefPosXY = Geometry::convertLatLngToXY($thiefPos);

                    if (isset($destinationsConfirmed[$thief->id])) {

                        foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed6) {

                            $destXY = Geometry::convertLatLngToXY($destinationConfirmed6);

                            $policemanDistanceCoefficient = null;

                            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                                $maxTempDistance = null;

                                foreach ($visiblePolicemenByThieves as $visiblePolicemenByThief) {

                                    foreach ($visiblePolicemenByThief as $visiblePolicemanByThief) {

                                        $c1['x'] = $visiblePolicemanByThief['longitude'];
                                        $c1['y'] = $visiblePolicemanByThief['latitude'];

                                        $c1XY = Geometry::convertLatLngToXY($c1);

                                        if (Geometry::checkIfPointBelongsToSegment($c1XY, $thiefPosXY, $destXY)) {

                                            $pointAndLineIntersection = Geometry::findIntersectionPointAndLine($c1XY, $thiefPosXY, $destXY);
                                            $pointAndLineIntersectionLatLon = Geometry::convertXYToLatLng($pointAndLineIntersection);
                                            $tempDistance = Geometry::getSphericalDistanceBetweenTwoPoints($pointAndLineIntersectionLatLon, $c1);

                                            if ($maxTempDistance === null || $tempDistance > $maxTempDistance) {
                                                $maxTempDistance = $tempDistance;
                                            }
                                        }
                                    }
                                }

                                foreach ($visiblePolicemenByThieves as $visiblePolicemenByThief) {

                                    foreach ($visiblePolicemenByThief as $visiblePolicemanByThief) {

                                        $policemanBigRadius = $this->getPolicemanRadius($room->config, $visiblePolicemanByThief['role'], false)['r'];
                                        $policemanSmallRadius = $this->getPolicemanRadius($room->config, $visiblePolicemanByThief['role'], true)['r'];

                                        $c1['x'] = $visiblePolicemanByThief['longitude'];
                                        $c1['y'] = $visiblePolicemanByThief['latitude'];

                                        $c1XY = Geometry::convertLatLngToXY($c1);

                                        if (Geometry::checkIfPointBelongsToSegment($c1XY, $thiefPosXY, $destXY)) {

                                            $pointAndLineIntersection = Geometry::findIntersectionPointAndLine($c1XY, $thiefPosXY, $destXY);
                                            $pointAndLineIntersectionLatLon = Geometry::convertXYToLatLng($pointAndLineIntersection);
                                            $tempDistance = Geometry::getSphericalDistanceBetweenTwoPoints($pointAndLineIntersectionLatLon, $c1);

                                            $tempDistanceInverted = $maxTempDistance - $tempDistance;
                                            $tempDistanceSmall = $tempDistance - $policemanSmallRadius;
                                            $tempDistanceBig = $tempDistance - $policemanBigRadius;

                                            if ($tempDistanceSmall <= 0) {
                                                $tempDistanceCoefficient = $tempDistanceInverted;
                                            } else if ($tempDistanceBig <= 0) {
                                                $tempDistanceCoefficient = $tempDistanceInverted * env('BOT_THIEF_POLICEMAN_DISTANCE_COEFFICIENT');
                                            } else {
                                                $tempDistanceCoefficient = $tempDistanceInverted * env('BOT_THIEF_POLICEMAN_DISTANCE_COEFFICIENT') * env('BOT_THIEF_AWAY_DISTANCE_COEFFICIENT');
                                            }

                                            if ($policemanDistanceCoefficient === null) {
                                                $policemanDistanceCoefficient = $tempDistanceCoefficient;
                                            } else {
                                                $policemanDistanceCoefficient += $tempDistanceCoefficient;
                                            }
                                        }
                                    }
                                }

                            } else {

                                $maxTempDistance = null;

                                foreach ($visiblePolicemenByThieves['all'] as $visiblePolicemanByThief) {

                                    $c1['x'] = $visiblePolicemanByThief['longitude'];
                                    $c1['y'] = $visiblePolicemanByThief['latitude'];

                                    $c1XY = Geometry::convertLatLngToXY($c1);

                                    if (Geometry::checkIfPointBelongsToSegment($c1XY, $thiefPosXY, $destXY)) {

                                        $pointAndLineIntersection = Geometry::findIntersectionPointAndLine($c1XY, $thiefPosXY, $destXY);
                                        $pointAndLineIntersectionLatLon = Geometry::convertXYToLatLng($pointAndLineIntersection);
                                        $tempDistance = Geometry::getSphericalDistanceBetweenTwoPoints($pointAndLineIntersectionLatLon, $c1);

                                        if ($maxTempDistance === null || $tempDistance > $maxTempDistance) {
                                            $maxTempDistance = $tempDistance;
                                        }
                                    }
                                }

                                foreach ($visiblePolicemenByThieves['all'] as $visiblePolicemanByThief) {

                                    $policemanBigRadius = $this->getPolicemanRadius($room->config, $visiblePolicemanByThief['role'], false)['r'];
                                    $policemanSmallRadius = $this->getPolicemanRadius($room->config, $visiblePolicemanByThief['role'], true)['r'];

                                    $c1['x'] = $visiblePolicemanByThief['longitude'];
                                    $c1['y'] = $visiblePolicemanByThief['latitude'];

                                    $c1XY = Geometry::convertLatLngToXY($c1);

                                    if (Geometry::checkIfPointBelongsToSegment($c1XY, $thiefPosXY, $destXY)) {

                                        $pointAndLineIntersection = Geometry::findIntersectionPointAndLine($c1XY, $thiefPosXY, $destXY);
                                        $pointAndLineIntersectionLatLon = Geometry::convertXYToLatLng($pointAndLineIntersection);
                                        $tempDistance = Geometry::getSphericalDistanceBetweenTwoPoints($pointAndLineIntersectionLatLon, $c1);

                                        $tempDistanceInverted = $maxTempDistance - $tempDistance;
                                        $tempDistanceSmall = $tempDistance - $policemanSmallRadius;
                                        $tempDistanceBig = $tempDistance - $policemanBigRadius;

                                        if ($tempDistanceSmall <= 0) {
                                            $tempDistanceCoefficient = $tempDistanceInverted;
                                        } else if ($tempDistanceBig <= 0) {
                                            $tempDistanceCoefficient = $tempDistanceInverted * env('BOT_THIEF_POLICEMAN_DISTANCE_COEFFICIENT');
                                        } else {
                                            $tempDistanceCoefficient = $tempDistanceInverted * env('BOT_THIEF_POLICEMAN_DISTANCE_COEFFICIENT') * env('BOT_THIEF_AWAY_DISTANCE_COEFFICIENT');
                                        }

                                        if ($policemanDistanceCoefficient === null) {
                                            $policemanDistanceCoefficient = $tempDistanceCoefficient;
                                        } else {
                                            $policemanDistanceCoefficient += $tempDistanceCoefficient;
                                        }
                                    }
                                }
                            }

                            if ($policemanDistanceCoefficient === null) {
                                $destinationConfirmed6['policemanDistance'] = -1;
                            } else {
                                $destinationConfirmed6['policemanDistance'] = $policemanDistanceCoefficient;
                            }
                        }

                        $maxPolicemanDistance = null;

                        foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed7) {
                            if ($maxPolicemanDistance === null || $maxPolicemanDistance < $destinationConfirmed7['policemanDistance']) {
                                $maxPolicemanDistance = $destinationConfirmed7['policemanDistance'];
                            }
                        }

                        foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed8) {
                            if ($maxPolicemanDistance > 0) {
                                $destinationConfirmed8['policemanDistanceCoefficient'] = 1 - ($destinationConfirmed8['policemanDistance'] / $maxPolicemanDistance);
                            } else {
                                $destinationConfirmed8['policemanDistanceCoefficient'] = 1;
                            }
                        }

                        $bestCoefficient = null;
                        $bestCoefficientId = null;

                        foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed9) {

                            // $safeRoadCoefficient = (1-$timeLapse) * 0.5 + 0.25;
                            // $safeDestinationCoefficient = $timeLapse * 0.5 + 0.25;

                            $safeRoadCoefficient = 0.8;
                            $safeDestinationCoefficient = 0.2;

                            $safeRoad = $destinationConfirmed9['policemanDistanceCoefficient'];
                            // $safeDestination = 0.1 * $destinationConfirmed9['disclosureDistanceCoefficient'] + 0.25 * $destinationConfirmed9['distanceToCenterCoefficient'] + 0.45 * $destinationConfirmed9['maxDistanceCoefficient'] + 0.2 * $destinationConfirmed9['lastDisclosureDistanceCoefficient'];
                            $safeDestination = 0.05 * $destinationConfirmed9['disclosureDistanceCoefficient'] + 0.1 * $destinationConfirmed9['distanceToCenterCoefficient'] + 0.45 * $destinationConfirmed9['maxDistanceCoefficient'] + 0.4 * $destinationConfirmed9['lastDisclosureDistanceCoefficient'];

                            $finalCoefficient = $safeRoadCoefficient * $safeRoad + $safeDestinationCoefficient * $safeDestination;
                            $destinationConfirmed9['finalCoefficient'] = $finalCoefficient;
                        }

                        foreach ($destinationsConfirmed[$thief->id] as $destinationConfirmed11) {
                            if ($bestCoefficient === null || $bestCoefficient < $destinationConfirmed11['finalCoefficient']) {
                                $bestCoefficient = $destinationConfirmed11['finalCoefficient'];
                                $bestCoefficientId = [
                                    'x' => $destinationConfirmed11['x'],
                                    'y' => $destinationConfirmed11['y'],
                                ];
                            }
                        }

                        $thiefDecision[$thief->id] = [
                            'x' => $bestCoefficientId['x'],
                            'y' => $bestCoefficientId['y'],
                        ];

                    } else {

                        $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);
                        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
                        $polygonCenter = substr($polygonCenter[0]->polygonCenter, 6, -1);

                        $finalPoint = explode(' ', $polygonCenter);

                        $destPoint['x'] = $finalPoint[0];
                        $destPoint['y'] = $finalPoint[1];

                        $thiefDecision[$thief->id] = [
                            'x' => $destPoint['x'],
                            'y' => $destPoint['y'],
                        ];
                    }

                } else {

                    $bestCoefficient = null;
                    $bestCoefficientId = null;

                    if (isset($destinationsConfirmed[$thief->id])) {

                        foreach ($destinationsConfirmed[$thief->id] as &$destinationConfirmed9) {

                            $safeDestinationCoefficient = 1;
                            $safeDestination = 0.25 * $destinationConfirmed9['disclosureDistanceCoefficient'] + 0 * $destinationConfirmed9['distanceToCenterCoefficient'] + 0.75 * $destinationConfirmed9['maxDistanceCoefficient'] + 0 * $destinationConfirmed9['lastDisclosureDistanceCoefficient'];

                            $finalCoefficient = $safeDestinationCoefficient * $safeDestination;
                            $destinationConfirmed9['finalCoefficient'] = $finalCoefficient;
                        }

                        foreach ($destinationsConfirmed[$thief->id] as $destinationConfirmed11) {
                            if ($bestCoefficient === null || $bestCoefficient < $destinationConfirmed11['finalCoefficient']) {
                                $bestCoefficient = $destinationConfirmed11['finalCoefficient'];
                                $bestCoefficientId = [
                                    'x' => $destinationConfirmed11['x'],
                                    'y' => $destinationConfirmed11['y'],
                                ];
                            }
                        }

                        $thiefDecision[$thief->id] = [
                            'x' => $bestCoefficientId['x'],
                            'y' => $bestCoefficientId['y'],
                        ];

                    } else {

                        $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);
                        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
                        $polygonCenter = substr($polygonCenter[0]->polygonCenter, 6, -1);

                        $finalPoint = explode(' ', $polygonCenter);

                        $destPoint['x'] = $finalPoint[0];
                        $destPoint['y'] = $finalPoint[1];

                        $thiefDecision[$thief->id] = [
                            'x' => $destPoint['x'],
                            'y' => $destPoint['y'],
                        ];
                    }
                }
            }

            if (now() >= $room->game_started_at) {

                $thievesTicketRefresh = $room->config['duration']['scheduled'] / env('BOT_REFRESH');
                $remainingTime = strtotime($room->game_ended_at) - strtotime(now());

                if ($remainingTime < 0) {
                    $remainingTime = 0;
                }

                foreach ($thieves as $tx2) {

                    $thiefAvailableTickets = ($tx2->config['black_ticket']['number'] + $tx2->config['fake_position']['number']) / 0.625;

                    if ($thiefAvailableTickets != 0) {
                        $timeIntervalBetweenTickets = floor($thievesTicketRefresh / $thiefAvailableTickets);
                    }

                    $sumAvailableTickets = ($tx2->config['black_ticket']['number'] - $tx2->config['black_ticket']['used_number']) * $room->config['actor']['thief']['black_ticket']['duration'] + ($tx2->config['fake_position']['number'] - $tx2->config['fake_position']['used_number']) * $room->config['actor']['thief']['fake_position']['duration'];

                    if (isset($timeIntervalBetweenTickets) && $globalCounter % $timeIntervalBetweenTickets == 0 || $sumAvailableTickets >= $remainingTime) {

                        if (($tx2->fake_position_finished_at === null || now() > $tx2->fake_position_finished_at) &&
                            ($tx2->black_ticket_finished_at === null || now() > $tx2->black_ticket_finished_at))
                        {
                            $blackTicketToUsed = 0;
                            $fakePositionToUsed = 0;

                            if ($tx2->config['black_ticket']['number'] - $tx2->config['black_ticket']['used_number'] > 0) {
                                $blackTicketToUsed = $tx2->config['black_ticket']['number'] - $tx2->config['black_ticket']['used_number'];
                            }

                            if ($tx2->config['fake_position']['number'] - $tx2->config['fake_position']['used_number'] > 0) {
                                $fakePositionToUsed = $tx2->config['fake_position']['number'] - $tx2->config['fake_position']['used_number'];
                            }

                            if ($blackTicketToUsed + $fakePositionToUsed > 0) {

                                $timeLapseRand = round(rand(round(200 * $timeLapse) - 100, 100) / 100);
                                $timeLapseRand = $timeLapseRand >= 0 ? $timeLapseRand : 0;

                                if ($timeLapseRand == 1) {

                                    $randTicket = rand(1, $blackTicketToUsed + $fakePositionToUsed);

                                    if ($blackTicketToUsed - $randTicket < 0) {

                                        if (isset($destinationsConfirmed[$tx2->id])) {

                                            $countDestin = count($destinationsConfirmed[$tx2->id]);
                                            $randDestin = rand(0, $countDestin-1);

                                            $finLoc = "{$destinationsConfirmed[$tx2->id][$randDestin]['x']} {$destinationsConfirmed[$tx2->id][$randDestin]['y']}";

                                            $tempConfig = $tx2->config;
                                            $tempConfig['fake_position']['used_number'] = $tx2->config['fake_position']['used_number'] + 1;
                                            $tx2->config = $tempConfig;

                                            $tx2->fake_position = DB::raw("ST_GeomFromText('POINT($finLoc)')");
                                            $tx2->fake_position_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['fake_position']['duration'] . ' seconds', strtotime(now())));
                                            $tx2->save();
                                        }

                                    } else {

                                        $tempConfig = $tx2->config;
                                        $tempConfig['black_ticket']['used_number'] = $tx2->config['black_ticket']['used_number'] + 1;
                                        $tx2->config = $tempConfig;

                                        $tx2->black_ticket_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['black_ticket']['duration'] . ' seconds', strtotime(now())));
                                        $tx2->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $botFinalPositions = [];

            foreach ($thieves as $t) {

                if ($t->hidden_position !== null) {

                    $botShift = $room->config['other']['bot_speed'] * env('BOT_REFRESH');

                    $t->mergeCasts([
                        'hidden_position' => Point::class,
                    ]);

                    $thiefPos2['x'] = $t->hidden_position->longitude;
                    $thiefPos2['y'] = $t->hidden_position->latitude;
                    $thiefPos2XY = Geometry::convertLatLngToXY($thiefPos2);

                    $position = $thiefDecision[$t->id];
                    $positionXY = Geometry::convertLatLngToXY($position);

                    $finalPositionXY = Geometry::getShiftedPoint($thiefPos2XY, $positionXY, $botShift);

                    if (Geometry::checkIfPointBelongsToSegment($finalPositionXY, $thiefPos2XY, $positionXY)) {
                        $finalPositionLatLng = Geometry::convertXYToLatLng($finalPositionXY);
                        $finalPosition = "{$finalPositionLatLng['x']} {$finalPositionLatLng['y']}";
                        $botFinalPositions[$t->id] = $finalPosition;
                    }
                }
            }

            /** @var \App\Models\Player[] $thieves */
            $thieves2 = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            foreach ($thieves2 as $t2) {
                if (isset($botFinalPositions[$t2->id])) {
                    $finPos = $botFinalPositions[$t2->id];
                    $t2->hidden_position = DB::raw("ST_GeomFromText('POINT($finPos)')");
                    $t2->save();
                }
            }

            $globalCounter++;

        } while ($room->status == 'GAME_IN_PROGRESS');
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
            if ($destination['x'] == $equidistantPoint['x'] && $destination['y'] == $equidistantPoint['y']) {
                $equidistantPointExists = true;
                break;
            }
        }

        return $equidistantPointExists;
    }
}

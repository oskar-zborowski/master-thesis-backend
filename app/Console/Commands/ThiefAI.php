<?php

namespace App\Console\Commands;

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

            $visiblePolicemenByThieves = [];
            $destinations = [];

            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                foreach ($thieves as $thief) {

                    if ($thief->hidden_position !== null) {

                        $thief->mergeCasts([
                            'hidden_position' => Point::class,
                        ]);

                        $thiefHiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";
                        $policemen = DB::select(DB::raw("SELECT id, role, ST_AsText(global_position) AS globalPosition FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']}"));

                        foreach ($policemen as $policeman) {

                            if ($policeman->globalPosition !== null) {

                                $policemanGlobalPosition = explode(' ', substr($policeman->globalPosition, 6, -1));

                                $visiblePolicemenByThieves[$thief->id][$policeman->id] = [
                                    'role' => $policeman->role,
                                    'longitude' => $policemanGlobalPosition[0],
                                    'latitude' => $policemanGlobalPosition[1],
                                ];
                            }
                        }
                    }
                }

            } else {

                /** @var \App\Models\Player[] $policemen */
                $policemen = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE'])->get();

                foreach ($policemen as $policeman) {

                    if ($policeman->global_position !== null) {

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
            }

            if ($room->config['actor']['thief']['visibility_radius'] != -1) {

                foreach ($thieves as $thief) {

                    if (isset($visiblePolicemenByThieves[$thief->id])) {

                        $thief->mergeCasts([
                            'hidden_position' => Point::class,
                        ]);

                        $thiefHiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                        foreach ($visiblePolicemenByThieves[$thief->id] as $key => $value) {

                            $policemanGlobalPosition = "{$value['longitude']} {$value['latitude']}";

                            if ($value['role'] == 'EAGLE') {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']} AND ((role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                            } else {
                                $nearestPolicemen = DB::select(DB::raw("SELECT role, ST_AsText(global_position) AS globalPosition FROM players WHERE id <> $key AND room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' AND ST_Distance_Sphere(ST_GeomFromText('POINT($thiefHiddenPosition)'), global_position) <= {$room->config['actor']['thief']['visibility_radius']} AND ((role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > {$room->config['actor']['policeman']['visibility_radius']}) OR (role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) > 0)) ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($policemanGlobalPosition)'), global_position) ASC LIMIT 2"));
                            }

                            if (count($nearestPolicemen) == 2) {

                                $c1['x'] = $value['longitude'];
                                $c1['y'] = $value['latitude'];
                                $c1['r'] = $this->getPolicemanRadius($room->config, $value['role']);

                                $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                $c2['x'] = $circle2[0];
                                $c2['y'] = $circle2[1];
                                $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role);

                                $circle3 = explode(' ', substr($nearestPolicemen[1]->globalPosition, 6, -1));
                                $c3['x'] = $circle3[0];
                                $c3['y'] = $circle3[1];
                                $c3['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[1]->role);

                                $equidistantPoint = $this->findEquidistantPoint($c1, $c2, $c3);

                                if (!$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) {
                                    $destinations[$thief->id][] = [
                                        'x' => $equidistantPoint['x'],
                                        'y' => $equidistantPoint['y'],
                                        'r' => $equidistantPoint['r'],
                                    ];
                                }

                            } else if (count($nearestPolicemen) == 1) {

                                $c1['x'] = $value['longitude'];
                                $c1['y'] = $value['latitude'];
                                $c1['r'] = $this->getPolicemanRadius($room->config, $value['role']);

                                $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                                $c2['x'] = $circle2[0];
                                $c2['y'] = $circle2[1];
                                $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role);

                                $equidistantPoint = $this->findSegmentMiddle($c1, $c2, true);

                                if (!$this->checkPointRepetition($destinations[$thief->id], $equidistantPoint)) {
                                    $destinations[$thief->id][] = [
                                        'x' => $equidistantPoint['x'],
                                        'y' => $equidistantPoint['y'],
                                        'r' => $equidistantPoint['r'],
                                    ];
                                }
                            }
                        }
                    }
                }

            } else {

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
                        $c1['r'] = $this->getPolicemanRadius($room->config, $value['role']);

                        $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                        $c2['x'] = $circle2[0];
                        $c2['y'] = $circle2[1];
                        $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role);

                        $circle3 = explode(' ', substr($nearestPolicemen[1]->globalPosition, 6, -1));
                        $c3['x'] = $circle3[0];
                        $c3['y'] = $circle3[1];
                        $c3['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[1]->role);

                        $equidistantPoint = $this->findEquidistantPoint($c1, $c2, $c3);

                        if (!$this->checkPointRepetition($destinations['all'], $equidistantPoint)) {
                            $destinations['all'][] = [
                                'x' => $equidistantPoint['x'],
                                'y' => $equidistantPoint['y'],
                                'r' => $equidistantPoint['r'],
                            ];
                        }

                    } else if (count($nearestPolicemen) == 1) {

                        $c1['x'] = $value['longitude'];
                        $c1['y'] = $value['latitude'];
                        $c1['r'] = $this->getPolicemanRadius($room->config, $value['role']);

                        $circle2 = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                        $c2['x'] = $circle2[0];
                        $c2['y'] = $circle2[1];
                        $c2['r'] = $this->getPolicemanRadius($room->config, $nearestPolicemen[0]->role);

                        $equidistantPoint = $this->findSegmentMiddle($c1, $c2, true);

                        if (!$this->checkPointRepetition($destinations['all'], $equidistantPoint)) {
                            $destinations['all'][] = [
                                'x' => $equidistantPoint['x'],
                                'y' => $equidistantPoint['y'],
                                'r' => $equidistantPoint['r'],
                            ];
                        }
                    }
                }
            }

            $allThieves = [];

            if ($playersNumber >= 1) {

                $boundaryPoints = explode(',', $room->boundary_points);

                foreach ($boundaryPoints as $boundaryPoint) {

                    $nearestPoliceman = DB::select(DB::raw("SELECT ST_AsText(global_position) AS globalPosition FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($boundaryPoint)'), global_position) ASC LIMIT 1"));

                    $point = explode(' ', substr($nearestPoliceman[0]->globalPosition, 6, -1));
                    $p1['x'] = $point[0];
                    $p1['y'] = $point[1];

                    $boundaryPoint = explode(' ', $boundaryPoint);
                    $p2['x'] = $boundaryPoint[0];
                    $p2['y'] = $boundaryPoint[1];

                    $R = $this->distanceBetweenTwoPoints($p1, $p2);

                    $destinations[] = [
                        'x' => $p2['x'],
                        'y' => $p2['y'],
                        'R' => $R,
                    ];
                }

            } else {

                $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($room->boundary_points))'))) AS polygonCenter"));
                $point = explode(' ', substr($polygonCenter[0]->polygonCenter, 6, -1));
                $p1['x'] = $point[0];
                $p1['y'] = $point[1];

                $destinations[] = [
                    'x' => $p1['x'],
                    'y' => $p1['y'],
                    'R' => -1,
                ];
            }

            /** @var \App\Models\Player[] $thieves */
            $thieves = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            foreach ($thieves as $thief) {

                foreach ($destinations as $destination) {

                    $policemenRatio = -1;

                    if ($thief->hidden_position) {

                        $p1['x'] = $destination['x'];
                        $p1['y'] = $destination['y'];

                        $thief->mergeCasts([
                            'hidden_position' => Point::class,
                        ]);

                        $thiefPosition = explode(' ', $thief->hidden_position);

                        $p2['x'] = $thiefPosition[0];
                        $p2['y'] = $thiefPosition[1];

                        foreach ($players as $player) {

                            if ($player->global_position) {

                                $player->mergeCasts([
                                    'global_position' => Point::class,
                                ]);

                                $playerPosition = explode(' ', $player->global_position);

                                $p0['x'] = $playerPosition[0];
                                $p0['y'] = $playerPosition[1];

                                $this->pointDistanceFromLine($p0, $p1, $p2);
                            }
                        }
                    }

                    $allThieves[$thief->id][] = [
                        'x' => $destination['x'],
                        'y' => $destination['y'],
                        'R' => $destination['R'],
                        'policemenRatio' => $policemenRatio,
                    ];
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

    private function findEquidistantPoint(array $c1, array $c2, array $c3) {

        $c1M = $c1;

        $c1 = $this->convertLatLngToXY($c1);
        $c2 = $this->convertLatLngToXY($c2);
        $c3 = $this->convertLatLngToXY($c3);

        $kA = -pow($c1['r'], 2) + pow($c2['r'], 2) + pow($c1['x'], 2) - pow($c2['x'], 2) + pow($c1['y'], 2) - pow($c2['y'], 2);
        $kB = -pow($c1['r'], 2) + pow($c3['r'], 2) + pow($c1['x'], 2) - pow($c3['x'], 2) + pow($c1['y'], 2) - pow($c3['y'], 2);

        $d = $c1['x'] * ($c2['y'] - $c3['y']) + $c2['x'] * ($c3['y'] - $c1['y']) + $c3['x'] * ($c1['y'] - $c2['y']);

        if ($d != 0) {

            $e0 = ($kA * ($c1['y'] - $c3['y']) + $kB * ($c2['y'] - $c1['y'])) / (2 * $d);
            $e1 = -($c1['r'] * ($c2['y'] - $c3['y']) + $c2['r'] * ($c3['y'] - $c1['y']) + $c3['r'] * ($c1['y'] - $c2['y'])) / $d;

            $f0 = -($kA * ($c1['x'] - $c3['x']) + $kB * ($c2['x'] - $c1['x'])) / (2 * $d);
            $f1 = ($c1['r'] * ($c2['x'] - $c3['x']) + $c2['r'] * ($c3['x'] - $c1['x']) + $c3['r'] * ($c1['x'] - $c2['x'])) / $d;

            $g0 = pow($e0, 2) - 2 * $e0 * $c1['x'] + pow($f0, 2) - 2 * $f0 * $c1['y'] - pow($c1['r'], 2) + pow($c1['x'], 2) + pow($c1['y'], 2);
            $g1 = $e0 * $e1 - $e1 * $c1['x'] + $f0 * $f1 - $f1 * $c1['y'] - $c1['r'];
            $g2 = pow($e1, 2) + pow($f1, 2) - 1;

            $sqrt = pow($g1, 2) - $g0 * $g2;

            if ($g2 != 0 && $sqrt >= 0) {

                $r = (-sqrt($sqrt) - $g1) / $g2;
                $c['x'] = $e0 + $e1 * $r;
                $c['y'] = $f0 + $f1 * $r;

                $c = $this->convertXYToLatLng($c);
                $c['r'] = $this->getSphericalDistanceBetweenTwoPoints($c, $c1M) - $c1M['r'];
            }
        }

        if (!isset($c)) {
            $c = false;
        }

        return $c;
    }

    private function findSegmentMiddle(array $c1, array $c2, bool $includeRadius = false) {

        $c1M = $c1;

        $c1 = $this->convertLatLngToXY($c1);
        $c2 = $this->convertLatLngToXY($c2);

        if ($includeRadius) {
            $r = ($this->getCartesianDistanceBetweenTwoPoints($c1, $c2) - $c1['r'] - $c2['r']) / 2;
            $c = $this->getShiftedPoint($c1, $c2, $c1['r'] + $r);
            $c = $this->convertXYToLatLng($c);
            $c['r'] = $this->getSphericalDistanceBetweenTwoPoints($c, $c1M) - $c1M['r'];
        } else {
            $c['x'] = ($c1['x'] + $c2['x']) / 2;
            $c['y'] = ($c1['y'] + $c2['y']) / 2;
            $c = $this->convertXYToLatLng($c);
            $c['r'] = $this->getSphericalDistanceBetweenTwoPoints($c, $c1M);
        }

        return $c;
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

    private function getShiftedPoint(array $p1, array $p2, float $distance) {

        $p12Distance = $this->getCartesianDistanceBetweenTwoPoints($p1, $p2);

        if ($p12Distance > 0) {
            $p12ShiftedPoint['x'] = $p1['x'] - ($distance * ($p1['x'] - $p2['x'])) / $p12Distance;
            $p12ShiftedPoint['y'] = $p1['y'] - ($distance * ($p1['y'] - $p2['y'])) / $p12Distance;
        } else {
            $p12ShiftedPoint['x'] = $p1['x'];
            $p12ShiftedPoint['y'] = $p1['y'];
        }

        return $p12ShiftedPoint;
    }

    private function getCartesianDistanceBetweenTwoPoints(array $p1, array $p2) {
        return sqrt(pow($p2['x'] - $p1['x'], 2) + pow($p2['y'] - $p1['y'], 2));
    }

    private function getSphericalDistanceBetweenTwoPoints(array $p1, array $p2) {

        $lat1 = deg2rad($p1['y']);
        $lng1 = deg2rad($p1['x']);

        $lat2 = deg2rad($p2['y']);
        $lng2 = deg2rad($p2['x']);

        $dLng = $lng2 - $lng1;

        $sLat1 = sin($lat1);
        $cLat1 = cos($lat1);

        $sLat2 = sin($lat2);
        $cLat2 = cos($lat2);

        $sdLng = sin($dLng);
        $cdLng = cos($dLng);

        $numerator = sqrt(pow($cLat2 * $sdLng, 2) + pow($cLat1 * $sLat2 - $sLat1 * $cLat2 * $cdLng, 2));
        $denominator = $sLat1 * $sLat2 + $cLat1 * $cLat2 * $cdLng;

        return atan2($numerator, $denominator) * 6371.009;
    }

    // TODO Dorobić sprawdzenie czy punkty nie leżą w tym samym miejscu - przyda się to do określania jak daleko ma
    // dana pozycja do granicy obwiedni oraz jak daleko ma środek mapy do najbliższej lini granicy - chociaż
    // nie wiem czy bardziej nie przydałoby się wyliczyć odległości odcinka do odcinka korzystając z funkcji mysql
    private function getCartesianDistanceFromPointToLine(array $p0, array $p1, array $p2) {
        return abs(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) * $p0['x'] + $p0['y'] + ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) * $p1['x'] - $p1['y']) / sqrt(pow(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']), 2) + 1);
    }

    /**
     * Konwersja EPSG:4326 na EPSG:3857
     */
    private function convertLatLngToXY(array $p1) {

        $smRadius = 6378136.98;
        $smRange = $smRadius * pi() * 2;

        if ($p1['y'] > 86) {
            $p['y'] = $smRange;
        } else if ($p1['y'] < -86) {
            $p['y'] = -$smRange;
        } else {

            if ($p1['y'] == -90) {
                $p1['y'] = -89.99999;
            } else if ($p1['y'] == 90) {
                $p1['y'] = 89.99999;
            }

            $p['y'] = log(tan((90 + $p1['y']) * pi() / 360)) * 20037508.34 / pi();
        }

        $p['x'] = $p1['x'] * 20037508.34 / 180;

        if (isset($p1['r'])) {
            $p['r'] = $p1['r'];
        }

        return $p;
    }

    /**
     * Konwersja EPSG:3857 na EPSG:4326
     */
    private function convertXYToLatLng(array $p1) {

        $p['x'] = $p1['x'] * 180 / 20037508.34;
        $p['y'] = atan(exp($p1['y'] * pi() / 20037508.34)) * 360 / pi() - 90;

        if (isset($p1['r'])) {
            $p['r'] = $p1['r'];
        }

        return $p;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

            /** @var \App\Models\Player[] $players */
            $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE'])->get();
            $playersNumber = count($players);

            $destinations = [];
            $allThieves = [];

            if ($playersNumber >= 3) {

                foreach ($players as $player) {

                    $player->mergeCasts([
                        'global_position' => Point::class,
                    ]);

                    $globalPosition = "{$player->global_position->longitude} {$player->global_position->latitude}";

                    $nearestPolicemen = DB::select(DB::raw("SELECT ST_AsText(global_position) AS globalPosition FROM players WHERE room_id = $room->id AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role <> 'THIEF' AND role <> 'AGENT' ORDER BY ST_Distance_Sphere(ST_GeomFromText('POINT($globalPosition)'), global_position) ASC LIMIT 2"));

                    $point = explode(' ', substr($nearestPolicemen[0]->globalPosition, 6, -1));
                    $p1['x'] = $point[0];
                    $p1['y'] = $point[1];

                    $point = explode(' ', substr($nearestPolicemen[1]->globalPosition, 6, -1));
                    $p2['x'] = $point[0];
                    $p2['y'] = $point[1];

                    $point = explode(' ', $globalPosition);
                    $p3['x'] = $point[0];
                    $p3['y'] = $point[1];

                    $R = $this->circleRadius($p1, $p2, $p3);
                    $middle = $this->findMiddle($p1, $p2, $p3);
                    $middleExists = false;

                    foreach ($destinations as $destination) {
                        if ($destination['x'] != $middle['x'] || $destination['y'] != $middle['y'] || $destination['R'] != $R) {
                            $middleExists = true;
                            break;
                        }
                    }

                    if (!$middleExists) {
                        $destinations[] = [
                            'x' => $middle['x'],
                            'y' => $middle['y'],
                            'R' => $R,
                        ];
                    }

                    if ($playersNumber == 3) {
                        break;
                    }
                }

            } else if ($playersNumber == 2) {

                $points = null;

                foreach ($players as $player) {

                    $player->mergeCasts([
                        'global_position' => Point::class,
                    ]);

                    $points[] = [
                        'x' => $player->global_position->longitude,
                        'y' => $player->global_position->latitude,
                    ];
                }

                $R = $this->distanceBetweenTwoPoints($points[0], $points[1]) / 2;
                $middle = $this->sectionMiddle($points[0], $points[1]);

                $destinations[] = [
                    'x' => $middle['x'],
                    'y' => $middle['y'],
                    'R' => $R,
                ];
            }

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

    private function circleRadius(array $p1, array $p2, array $p3) {

        $a = $this->distanceBetweenTwoPoints($p1, $p2);
        $b = $this->distanceBetweenTwoPoints($p2, $p3);
        $c = $this->distanceBetweenTwoPoints($p3, $p1);

        $p = ($a + $b + $c) / 2;

        return ($a * $b * $c) / (4 * sqrt($p * ($p - $a) * ($p - $b) * ($p - $c)));
    }

    private function findMiddle(array $p1, array $p2, array $p3) {

        $aMiddle = $this->sectionMiddle($p1, $p2);
        $bMiddle = $this->sectionMiddle($p2, $p3);

        $center['x'] = ($bMiddle['y'] - $aMiddle['y'] + ($p2['x'] - $p3['x']) / ($p2['y'] - $p3['y']) * $bMiddle['x'] + ($p2['x'] - $p1['x']) / ($p1['y'] - $p2['y']) * $aMiddle['x']) / (($p2['x'] - $p1['x']) / ($p1['y'] - $p2['y']) + ($p2['x'] - $p3['x']) / ($p2['y'] - $p3['y']));
        $center['y'] = ($p2['x'] - $p1['x']) / ($p1['y'] - $p2['y']) * $center['x'] + $aMiddle['y'] + ($p1['x'] - $p2['x']) / ($p1['y'] - $p2['y']) * $aMiddle['x'];

        return $center;
    }

    private function pointDistanceFromLine(array $p0, array $p1, array $p2) {
        return abs(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) * $p0['x'] + $p0['y'] + ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) * $p1['x'] - $p1['y']) / sqrt(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) * ($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) + 1);
    }

    private function distanceBetweenTwoPoints(array $p1, array $p2) {
        return sqrt(($p2['x'] - $p1['x']) * ($p2['x'] - $p1['x']) + ($p2['y'] - $p1['y']) * ($p2['y'] - $p1['y']));
    }

    private function sectionMiddle(array $p1, array $p2) {

        $center['x'] = ($p1['x'] + $p2['x']) / 2;
        $center['y'] = ($p1['y'] + $p2['y']) / 2;

        return $center;
    }
}

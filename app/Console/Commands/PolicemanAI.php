<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PolicemanAI extends Command
{
    private const CLOSE_DISTANCE_DELTA = 20;

    /** The name and signature of the console command.*/
    protected $signature = 'policeman-ai:start {roomId}';

    /** The console command description. */
    protected $description = 'Start the Policeman AI';

    private array $policeCenter;

    private Room $room;

    /** Execute the console command. */
    public function handle()
    {
        $roomId = $this->argument('roomId');

        do {
            $this->room = Room::where('id', $roomId)->first();
            /** @var Player[] $policemen */
            $policemen = $this->room
                ->players()
                ->where(['is_bot' => true,])
                ->where('role', '!=', 'THIEF')
                ->get();


            $this->handleSettingStartPositions();

            $thievesPosition = $this->getThievesPosition($policemen);
            if (empty($thievesPosition)) {
                // search for thieves
            } else {
                $targetThiefId = $this->getNearestThief($policemen, $thievesPosition);
                $this->goToThief($thievesPosition[$targetThiefId], $policemen);
            }

        } while ('GAME_IN_PROGRESS' === $this->room->status);
    }

    private function handleSettingStartPositions()
    {
        /** @var Player[] $policemenWithoutLocation */
        $policemenWithoutLocation = $this->room
            ->players()
            ->whereNull('hidden_position')
            ->where(['is_bot' => true])
            ->where('role', '!=', 'THIEF')
            ->get();
        if (0 === count($policemenWithoutLocation)) {
            return;
        }

        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
        $polygonCenterString = substr($polygonCenter[0]->polygonCenter, 6, -1);
        foreach ($policemenWithoutLocation as $policeman) {
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($polygonCenterString)')");
            $policeman->save();
        }
    }

    /**
     * @param Player[] $policemen
     * @return array
     */
    private function getThievesPosition(\Collection $policemen): array
    {
        $thievesPosition = [];
        foreach ($policemen as $policeman) {
            $policemanPosition = "{$policeman->hidden_position->longitude} {$policeman->hidden_position->latitude}";
            $visibilityRadius = $this->room->config['actor']['policeman']['visibility_radius'];
            if ('EAGLE' === $policeman->role) {
                $visibilityRadius *= 2;
            }

            if (-1 !== $visibilityRadius) {
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(global_position) AS globalPosition FROM players
WHERE room_id = $this->room->id AND global_position IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  AND ST_Distance_Sphere(ST_GeomFromText('POINT($policemanPosition)'), global_position) <= $visibilityRadius
  "));
                foreach ($thieves as $thief) {
                    $position = explode(' ', substr($thief->globalPosition, 6, -1));
                    $thievesPosition[$thief->id] = [
                        'x' => $position[0],
                        'y' => $position[1],
                    ];
                }

                return $thievesPosition;
            } else {
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(global_position) AS globalPosition FROM players
WHERE room_id = $this->room->id AND global_position IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  "));
            }

            foreach ($thieves as $thief) {
                $position = explode(' ', substr($thief->globalPosition, 6, -1));
                $thievesPosition[$thief->id] = [
                    'x' => $position[0],
                    'y' => $position[1],
                ];
            }
        }

        return $thievesPosition;
    }

    /**
     * @param Player[] $policemen
     * @param array $thievesPositions
     * @return int
     */
    private function getNearestThief(\Collection $policemen, array $thievesPositions): int
    {
        $policeCenter = $this->getPoliceCenter($policemen);
        $closestThiefId = null;
        $closestThiefDistans = null;
        foreach ($thievesPositions as $playerId => $thief) {
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints(
                [
                    'x' => $thief['longitude'],
                    'y' => $thief['latitude'],
                ],
                $policeCenter,
            );
            if (null === $closestThiefDistans || $closestThiefDistans > $distance) {
                $closestThiefDistans = $distance;
                $closestThiefId = $playerId;
            }
        }

        return $closestThiefId;
    }

    /** @param Player[] $policemen */
    private function getPoliceCenter(\Collection $policemen): array
    {
        $longitude = 0.0;
        $latitude = 0.0;
        $pointsNumber = 0;
        foreach ($policemen as $policeman) {
            if (null !== $policemen->hidden_position->longitude || null !== $policemen->hidden_position->latitude) {
                continue;
            }

            $longitude += $policeman->hidden_position->longitude;
            $latitude += $policeman->hidden_position->latitude;
            $pointsNumber++;
        }

        $this->policeCenter = [
            'x' => $longitude / $pointsNumber,
            'y' => $latitude / $pointsNumber,
        ];
        return $this->policeCenter;
    }

    /**
     * @param array $targetThief
     * @param Player[] $policemen
     */
    private function goToThief(array $targetThief, \Collection $policemen)
    {
        $targetPositions = [];
        $catchingSmallRadius = 0.8 * $this->room->config['actor']['policeman']['catching']['radius'];
        $halfWayRadius = 0.5 * Geometry::getSphericalDistanceBetweenTwoPoints($this->policeCenter, $targetThief);
        $goToCatching = $catchingSmallRadius > $halfWayRadius;
        $policemenObject = $this->getReorderedPoliceLocation($policemen, $targetThief);
        if (1 === count($policemenObject)) {
            $targetPositions[$policemenObject[0]['playerId']] = $targetThief;
        } else {
            $halfWayPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $halfWayRadius, count($policemenObject));
            $catchingPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $catchingSmallRadius, count($policemenObject));
            $catchingEvenlySpreadPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $catchingSmallRadius, count($policemenObject), true);
            foreach ($policemenObject as $key => $policemanObject) {
                if ($goToCatching) {
                    $distanceToUneven = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $catchingPoints[$key]);
                    if (self::CLOSE_DISTANCE_DELTA > $distanceToUneven) {
                        // go to even
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$key], $targetThief);
                    } else {
                        // go to unEven
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingPoints[$key], $targetThief);
                    }
                } else {
                    // go to halfWay uneven
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($halfWayPoints[$key], $targetThief);
                }
            }
        }

        $this->makeAStep($targetPositions, $policemen);
    }

    /**
     * Order officers from right to left
     * @param Player[] $policemen
     * @param array $thief
     * @return array
     */
    private function getReorderedPoliceLocation(\Collection $policemen, array $thief): array
    {
        function order($a, $b): int
        {
            return ($a['order'] < $b['order']) ? -1 : 1;
        }

        $newOrder = [];
        foreach ($policemen as $policeman) {
            $policemanPosition = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $angle = Geometry::getAngleMadeOfPoints($this->policeCenter, $thief, $policemanPosition);
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thief, $policemanPosition);
            $newOrder[] = [
                'order' => $distance * sin($angle),
                'position' => $policemanPosition,
                'playerId' => $policeman->id,
            ];
        }

        usort($newOrder, 'order');
        $policeArray = [];
        foreach ($newOrder as $key => $value) {
            $policeArray[$key] = $newOrder[$key]['officer'];
        }

        return $policeArray;
    }

    private function getPointsOnCircle(array $center, array $reference, float $radius, int $n, bool $isEvenlySpread = false): array
    {
        $points = [];
        $angleDelta = 2 * pi() / $n;
        if (!$isEvenlySpread) {
            $angleDelta *= 1 - pow(1.7, -$n);
        }

        for ($i = 0; $i < $n; $i++) {
            $angle = $angleDelta * ($i - ($n - 1) / 2);
            $centerCartesian = Geometry::convertLatLngToXY($center);
            $referenceCartesian = Geometry::convertLatLngToXY($reference);
            $directionPoint = [
                'x' => $centerCartesian['x'] + ($referenceCartesian['x'] - $centerCartesian['x']) * cos($angle) - ($referenceCartesian['y'] - $centerCartesian['y']) * sin($angle),
                'y' => $centerCartesian['y'] + ($referenceCartesian['x'] - $centerCartesian['x']) * sin($angle) + ($referenceCartesian['y'] - $centerCartesian['y']) * cos($angle),
            ];
            $point = Geometry::getShiftedPoint($centerCartesian, $directionPoint, $radius);
            $points[] = Geometry::convertXYToLatLng($point);
        }

        return $points;
    }

    private function preventFromGoingOutside(array $target, array $thief): array
    {
        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
        $point = "{$target['x']} {$target['y']}";
        $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($point)')) AS isIntersects"));
        if ($isIntersects[0]->isIntersects) {
            return $target;
        } else {
            return $thief;
        }
    }

    /**
     * @param array $targetPositions
     * @param Player[] $policemen
     */
    private function makeAStep(array $targetPositions, \Collection $policemen)
    {
        $botShift = $this->room->config['other']['bot_speed'] * env('BOT_REFRESH');
        foreach ($policemen as $policeman) {
            $position = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $positionCartesian = Geometry::convertLatLngToXY($position);
            $targetCartesian = Geometry::convertLatLngToXY($targetPositions[$policeman->id]);
            $newPosition = Geometry::getShiftedPoint($positionCartesian, $targetCartesian, $botShift);
            $newPositionLatLng = Geometry::convertXYToLatLng($newPosition);
            $newPositionFormatted = "{$newPositionLatLng['x']} {$newPositionLatLng['y']}";
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($newPositionFormatted)')");
            $policeman->save();
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

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
            $this->handleSettingStartPositions();
            if ($this->room->game_started_at > now()) {
                continue;
            }

            $policemen = $this->room
                ->players()
                ->where(['is_bot' => true])
                ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
                ->get();

//            $targets = $this->getTargetOnTheWall($policemen);
//            if (strtotime($this->room->game_started_at) < strtotime(now())) {
//                foreach ($policemen as $policeman) {
//                    $point = $targets[$policeman->id];
//                    $pointStr = "{$point['x']} {$point['y']}";
//                    $policeman->black_ticket_finished_at = $this->room->game_started_at;
//                    $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($pointStr)')");
//                    $policeman->save();
//                }
//            }
//            $this->makeAStep($targets, $policemen);

            $thievesPosition = $this->getThievesPosition($policemen);
            $policemen[0]->warning_number = 1;
            $policemen[0]->save();
            if (empty($thievesPosition)) {
                // search for thieves
            } else {
                $targetThiefId = $this->getNearestThief($policemen, $thievesPosition);
                $policemen[0]->warning_number = $targetThiefId;
                $policemen[0]->save();
                $this->goToThief($thievesPosition[$targetThiefId], $policemen);
            }

        } while ('GAME_IN_PROGRESS' === $this->room->status);
    }

    private function getTargetOnTheWall($policemen): array
    {
        $boundaryPoints = explode(',', $this->room->boundary_points);
        $boundaryPoint = explode(' ', $boundaryPoints[0]);
        $target = [
            'x' => $boundaryPoint[0],
            'y' => $boundaryPoint[1],
        ];
        $targetOnTheWall = [];
        foreach ($policemen as $policeman) {
            $targetOnTheWall[$policeman->id] = $target;
        }

        return $targetOnTheWall;
    }

    private function handleSettingStartPositions()
    {
        /** @var Player[] $policemenWithoutLocation */
        $policemenWithoutLocation = $this->room
            ->players()
            ->whereNull('hidden_position')
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        if (0 === count($policemenWithoutLocation)) {
            return;
        }

        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
        $polygonCenter = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundary))'))) AS polygonCenter"));
        $polygonCenterString = substr($polygonCenter[0]->polygonCenter, 6, -1);
        $polygonCenterPoint = explode(' ', $polygonCenterString);
        $polygonCenterPoint = [
            'x' => $polygonCenterPoint[0],
            'y' => $polygonCenterPoint[1],
        ];
        $polygonCenterPoint = Geometry::convertXYToLatLng($polygonCenterPoint);
        $polygonCenterString = "{$polygonCenterPoint['x']} {$polygonCenterPoint['y']}";
        foreach ($policemenWithoutLocation as $policeman) {
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($polygonCenterString)')");
            $policeman->save();
        }
    }

    private function getThievesPosition(Collection $policemen): array
    {
        $thievesPosition = [];
        foreach ($policemen as $policeman) {
            $visibilityRadius = $this->room->config['actor']['policeman']['visibility_radius'];
            if ('EAGLE' === $policeman->role) {
                $visibilityRadius *= 2;
            }

            if (0 > $visibilityRadius) {
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(hidden_position) AS hiddenPosition FROM players
WHERE room_id = $this->room->id AND hidden_position IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  "));
                foreach ($thieves as $thief) {
                    $position = explode(' ', substr($thief->hiddenPosition, 6, -1));
                    $thievesPosition[$thief->id] = [
                        'x' => $position[0],
                        'y' => $position[1],
                    ];
                }

                return $thievesPosition;
            } else {
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(hidden_position) AS hiddenPosition FROM players
WHERE room_id = $this->room->id AND hidden_position IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  AND ST_Distance_Sphere(ST_GeomFromText('POINT($policeman->hidden_position)'), global_position) <= $visibilityRadius
  "));
            }

            foreach ($thieves as $thief) {
                $position = explode(' ', substr($thief->hiddenPosition, 6, -1));
                $thievesPosition[$thief->id] = [
                    'x' => $position[0],
                    'y' => $position[1],
                ];
            }
        }

        return $thievesPosition;
    }

    private function getNearestThief(Collection $policemen, array $thievesPositions): int
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

    private function getPoliceCenter(): array
    {
        $longitude = 0.0;
        $latitude = 0.0;
        $pointsNumber = 0;
        /** @var Player[] $policemen */
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        foreach ($policemen as $policeman) {
            if (null !== $policemen->hidden_position) {
                continue;
            }

            $policeman->mergeCasts(['hidden_position' => Point::class,]);
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

    private function goToThief(array $targetThief, Collection $policemen)
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
                $distanceToThief = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $targetThief);
                $distanceToHalfWay = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $halfWayPoints[$key]);
                $distanceToUneven = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $catchingPoints[$key]);
                if (!$goToCatching && $distanceToThief > $halfWayRadius && self::CLOSE_DISTANCE_DELTA < $distanceToHalfWay) {
                    // go to half way
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($halfWayPoints[$key], $targetThief);
                } elseif (self::CLOSE_DISTANCE_DELTA < $distanceToUneven) {
                    // go to uneven catch
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingPoints[$key], $targetThief);
                } else {
                    // go to even catch
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$key], $targetThief);
                }
            }
        }

        $this->makeAStep($targetPositions, $policemen);
    }

    private function getReorderedPoliceLocation(Collection $policemen, array $thief): array
    {
        function order($a, $b): int
        {
            return ($a['order'] < $b['order']) ? -1 : 1;
        }

        $newOrder = [];
        foreach ($policemen as $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class,]);
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

    private function makeAStep(array $targetPositions, Collection $policemen)
    {
        $botShift = $this->room->config['other']['bot_speed'] * env('BOT_REFRESH');
        $positions = [];
        foreach ($policemen as $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class,]);
            $position = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $positionCartesian = Geometry::convertLatLngToXY($position);
            $targetCartesian = Geometry::convertLatLngToXY($targetPositions[$policeman->id]);
            $newPosition = Geometry::getShiftedPoint($positionCartesian, $targetCartesian, $botShift);
            $newPositionLatLng = Geometry::convertXYToLatLng($newPosition);
            $positions[$policeman->id] = "{$newPositionLatLng['x']} {$newPositionLatLng['y']}";
        }

        /** @var Player[] $policemen */
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        foreach ($policemen as $policeman) {
            $position = $positions[$policeman->id];
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($position)')");
            $policeman->save();
        }
    }
}

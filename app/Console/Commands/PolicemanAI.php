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

    private array $thievesPositions = [];

    private $lastDisclosure;

    private array $catchingDirectionPoint;

    /** Execute the console command. */
    public function handle()
    {
        $roomId = $this->argument('roomId');
        $this->room = Room::where('id', $roomId)->first();
        $this->lastDisclosure = now();
        $this->handleSettingStartPositions();
        $this->updatePoliceCenter();
        $this->catchingDirectionPoint = $this->policeCenter;

        do {
            $this->test();
            sleep(env('BOT_REFRESH'));
            /** @var Room $room */
            $this->room = Room::where('id', $roomId)->first();
            if ($this->room->game_started_at > now()) {
                continue;
            }

            $policemen = $this->room
                ->players()
                ->where(['is_bot' => true])
                ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
                ->get();
            $this->updateThievesPosition();
            $this->updatePoliceCenter();
            if (0 < count($this->thievesPositions)) {
                $targetThiefId = $this->getNearestThief($this->thievesPositions);
                $this->goToThief($this->thievesPositions[$targetThiefId]);
            }

        } while ('GAME_IN_PROGRESS' === $this->room->status);
    }

    private function test()
    {
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();

        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
//        $point = "{$target1['x']} {$target1['y']}";
        $point = "0.0 0.0";
        $isInside = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($point)')) AS isIntersects"));
        if ($isInside[0]->isIntersects) {
            $policemen[0]->warning_number = 1;
        } else {
            $policemen[0]->warning_number = 2;
        }
    }

    private function getArrayWithTarget($target)
    {
        $array = [];
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        foreach ($policemen as $policeman) {
            $array[$policeman->id] = $target;
        }

        return $array;
    }

    private function getTargetOnTheWall($n = 0): array
    {
        $boundaryPoints = explode(',', $this->room->boundary_points);
        $boundaryPoint = explode(' ', $boundaryPoints[$n]);
        $target = [
            'x' => $boundaryPoint[0],
            'y' => $boundaryPoint[1],
        ];

        return $target;
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

    private function updateThievesPosition(): void
    {
        $thieves = $this->room
            ->players()
            ->where(['role' => 'THIEF'])
            ->whereNotNull('hidden_position')
            ->whereNull('caught_at')
            ->where(function ($query) {
                $query->where(['status' => 'CONNECTED'])
                    ->orWhere(['status' => 'DISCONNECTED']);
            })
            ->get();
        $positionos = [];
        foreach ($thieves as $thief) {
            $thief->mergeCasts(['hidden_position' => Point::class]);
            $thiefPosition = [
                'x' => $thief->hidden_position->longitude,
                'y' => $thief->hidden_position->latitude,
            ];
            $positions[$thief->id] = $thiefPosition;
        }

        $this->thievesPositions = $positions;

//        if ($this->lastDisclosure >= $this->room->next_disclosure_at) {
//            return;
//        }
//
//        $positions = [];
//        $this->lastDisclosure = $this->room->next_disclosure_at;
//        $thieves = $this->room
//            ->players()
//            ->where(['role' => 'THIEF'])
//            ->whereNotNull('hidden_position')
//            ->whereNull('caught_at')
//            ->where(function ($query) {
//                $query->where(['status' => 'CONNECTED'])
//                    ->orWhere(['status' => 'DISCONNECTED']);
//            })
//            ->get();
//        $policemen = $this->room
//            ->players()
//            ->where(['is_bot' => true])
//            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
//            ->get();
//        $visibilityRadius = $this->room->config['actor']['policeman']['visibility_radius'];
//        foreach ($thieves as $thief) {
//            $thief->mergeCasts(['hidden_position' => Point::class]);
//            $thiefPosition = [
//                'x' => $thief->hidden_position->longitude,
//                'y' => $thief->hidden_position->latitude,
//            ];
//            if (-1 === $visibilityRadius) {
//                $positions[$thief->id] = $thiefPosition;
//            } else {
//                foreach ($policemen as $policeman) {
//                    $policeman->mergeCasts(['hidden_position' => Point::class]);
//                    $multiplier = 'EAGLE' === $policeman->role ? 2 : 1;
//                    $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thiefPosition, [
//                        'x' => $policeman->hidden_position->longitude,
//                        'y' => $policeman->hidden_position->latitude,
//                    ]);
//                    if ($visibilityRadius * $multiplier > $distance) {
//                        $positions[$thief->id] = $thiefPosition;
//                        break;
//                    }
//                }
//            }
//        }
//
//        $this->thievesPositions = $positions;
    }

    private function getNearestThief(array $thievesPositions): int
    {
        $closestThiefId = null;
        $closestThiefDistance = null;
        foreach ($thievesPositions as $playerId => $thief) {
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thief, $this->policeCenter);
            if (null === $closestThiefDistance || $closestThiefDistance > $distance) {
                $closestThiefDistance = $distance;
                $closestThiefId = $playerId;
            }
        }

        return $closestThiefId;
    }

    private function updatePoliceCenter(): void
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
            $policeman->mergeCasts(['hidden_position' => Point::class]);
            if (null === $policeman->hidden_position->longitude || null === $policeman->hidden_position->latitude) {
                continue;
            }

            $longitude += $policeman->hidden_position->longitude;
            $latitude += $policeman->hidden_position->latitude;
            $pointsNumber++;
        }

        if (0 === $pointsNumber) {
            $this->policeCenter = ['x' => 0.0, 'y' => 0.0];
            return;
        }

        $this->policeCenter = [
            'x' => $longitude / $pointsNumber,
            'y' => $latitude / $pointsNumber,
        ];
    }

    private function goToThief(array $targetThief): void
    {
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        $targetPositions = [];
        $catchingSmallRadius = 0.8 * $this->room->config['actor']['policeman']['catching']['radius'];
        $halfWayRadius = 0.5 * Geometry::getSphericalDistanceBetweenTwoPoints($this->policeCenter, $targetThief);
        $policemen[0]->ping = $halfWayRadius;
        $policemen[0]->save();
        $policemen[1]->ping = $catchingSmallRadius;
        $policemen[1]->save();

        $goToCatching = $catchingSmallRadius > $halfWayRadius;
        $policemenObject = $this->getReorderedPoliceLocation($targetThief);
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
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($halfWayPoints[$key], $catchingPoints[$key], $targetThief);
                } elseif (self::CLOSE_DISTANCE_DELTA < $distanceToUneven) {
                    // go to uneven catch
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingPoints[$key], $targetThief, $targetThief);
                } else {
                    // go to even catch
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$key], $targetThief, $targetThief);
                }
            }
        }

        $this->makeAStep($targetPositions);
    }

    private function getReorderedPoliceLocation(array $thief): array
    {
        $newOrder = [];
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        foreach ($policemen as $key => $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class]);
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

        usort($newOrder, function ($a, $b) {
            return ($a['order'] < $b['order']) ? -1 : 1;
        });
        $policeArray = [];
        foreach ($newOrder as $value) {
            $policeArray[] = [
                'position' => $value['position'],
                'playerId' => $value['playerId'],
            ];
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
            $referenceDistance = Geometry::getSphericalDistanceBetweenTwoPoints($center, $reference);
            if (5 > $referenceDistance) {
                $reference = $this->catchingDirectionPoint;
            } else {
                $this->catchingDirectionPoint = $reference;
            }

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

    private function preventFromGoingOutside(array $target1, array $target2, array $target3): array
    {
        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
        $point = "{$target1['x']} {$target1['y']}";
        $isInside = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($point)')) AS isIntersects"));
        if (!$isInside[0]->isIntersects) {
            return $target1;
        } else {
            $point = "{$target2['x']} {$target2['y']}";
            $isInside = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($point)')) AS isIntersects"));

            return $isInside[0]->isIntersects ? $target3 : $target2;
        }
    }

    private function makeAStep(array $targetPositions)
    {
        $positions = [];
        $botShift = $this->room->config['other']['bot_speed'] * env('BOT_REFRESH');
        /** @var Player[] $policemen */
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();

        foreach ($policemen as $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class]);
            $position = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($position, $targetPositions[$policeman->id]);
            $distance = $distance > $botShift ? $botShift : $distance;
            $positionCartesian = Geometry::convertLatLngToXY($position);
            $targetCartesian = Geometry::convertLatLngToXY($targetPositions[$policeman->id]);
            $newPosition = Geometry::getShiftedPoint($positionCartesian, $targetCartesian, $distance);
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

    private function goToPoints($points)
    {
        /** @var Player[] $policemen */
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();

        foreach ($policemen as $policeman) {
            $position = $points[$policeman->id];
            $position = "{$position['x']} {$position['y']}";
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($position)')");
            $policeman->save();
        }
    }
}

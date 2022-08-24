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

    /** Execute the console command. */
    public function handle()
    {
        $roomId = $this->argument('roomId');
        $this->room = Room::where('id', $roomId)->first();
        $this->lastDisclosure = $this->room->next_disclosure_at;

        do {
            sleep(env('BOT_REFRESH'));
            /** @var Room $room */
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
            $this->updateThievesPosition();
            $this->updatePoliceCenter();
            if (0 === count($this->thievesPositions)) {
                // search for thieves
            } else {
                $targetThiefId = $this->getNearestThief($this->thievesPositions);
//                $this->goToThief($this->thievesPositions[$targetThiefId]);

//                $this->goToThief($this->getTargetOnTheWall());
                $this->makeAStep($this->getArrayWithTarget($this->thievesPositions[$targetThiefId]));
            }

        } while ('GAME_IN_PROGRESS' === $this->room->status);
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
//            $policeman->ping = $policeman->id;
//            $policeman->save();
        }

//        $policemen[1]->ping = $policemen[1]->id;
//        $policemen[1]->save();
        return $array;
    }

    private function getTargetOnTheWall(): array
    {
        $boundaryPoints = explode(',', $this->room->boundary_points);
        $boundaryPoint = explode(' ', $boundaryPoints[0]);
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
//        if ($this->lastDisclosure >= $this->room->next_disclosure_at) {
//            return;
//        }

        $positions = [];
        $this->lastDisclosure = $this->room->next_disclosure_at;
        $thieves = $this->room
            ->players()
            ->where(['role' => 'THIEF'])
            ->whereNotNull('hidden_position')
            ->where(function ($query) {
                $query->where(['status' => 'CONNECTED'])
                    ->orWhere(['status' => 'DISCONNECTED']);
            })
            ->get();
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        $visibilityRadius = $this->room->config['actor']['policeman']['visibility_radius'];
//        $policemen[0]->black_ticket_finished_at = $this->room->next_disclosure_at;
//        $policemen[0]->save();
        foreach ($thieves as $thief) {
            $thief->mergeCasts(['hidden_position' => Point::class]);
            $thiefPosition = [
                'x' => $thief->hidden_position->longitude,
                'y' => $thief->hidden_position->latitude,
            ];
            if (-1 === $visibilityRadius) {
                $positions[$thief->id] = $thiefPosition;
            } else {
                foreach ($policemen as $policeman) {
                    $policeman->mergeCasts(['hidden_position' => Point::class]);
                    $multiplier = 'EAGLE' === $policeman->role ? 2 : 1;
                    $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thiefPosition, [
                        'x' => $policeman->hidden_position->longitude,
                        'y' => $policeman->hidden_position->latitude,
                    ]);
                    if ($visibilityRadius * $multiplier > $distance) {
                        $positions[$thief->id] = $thiefPosition;
                        break;
                    }
                }
            }
        }

        $this->thievesPositions = $positions;
    }


    private function getThievesPosition(Collection $policemen): array
    {
        $thievesPosition = [];
        foreach ($policemen as $policeman) {
//            $policemen[1]->black_ticket_finished_at = $this->room->game_started_at;
//            $policemen[1]->save();
            $visibilityRadius = $this->room->config['actor']['policeman']['visibility_radius'];
//            $policemen[0]->black_ticket_finished_at = $this->room->game_started_at;
//            $policemen[0]->save();
//            $policemen[1]->warning_number = count($visibilityRadius + 2);
//            $policemen[1]->save();
            if ('EAGLE' === $policeman->role) {
                $visibilityRadius *= 2;
            }

            if (0 > $visibilityRadius) {
//                $policemen[0]->black_ticket_finished_at = $this->room->game_started_at;
//                $policemen[0]->save();
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(global_position) AS globalPosition FROM players
WHERE room_id = $this->room->id AND global_position IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  "));
                $policemen[1]->black_ticket_finished_at = $this->room->game_started_at;
                $policemen[1]->save();
                $policemen[1]->warning_number = count($thieves);
                $policemen[1]->save();
                foreach ($thieves as $thief) {
                    $position = explode(' ', substr($thief->globalPosition, 6, -1));
                    $policemen[1]->warning_number = count($position);
                    $policemen[1]->save();
                    $thievesPosition[$thief->id] = [
                        'x' => $position[0],
                        'y' => $position[1],
                    ];
                }

                return $thievesPosition;
            } else {
                $thieves = DB::select(DB::raw("
SELECT id, ST_AsText(global_position) AS globalPosition FROM players
WHERE room_id = $this->room->id AND globalPosition IS NOT NULL
  AND (status = 'CONNECTED' OR status = 'DISCONNECTED') AND role = 'THIEF'
  AND ST_Distance_Sphere(ST_GeomFromText('POINT($policeman->hidden_position)'), global_position) <= $visibilityRadius
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

    private function getNearestThief(array $thievesPositions): int
    {
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        $closestThiefId = null;
        $closestThiefDistance = null;
        foreach ($thievesPositions as $playerId => $thief) {
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thief, $this->policeCenter);
            if (null === $closestThiefDistance || $closestThiefDistance > $distance) {
//                $policemen[0]->warning_number = 1;
//                $policemen[0]->save();
                $closestThiefDistance = $distance;
                $closestThiefId = $playerId;
            } else {
//                $policemen[0]->warning_number = 2;
//                $policemen[0]->save();
            }
        }

        $policemen[0]->ping = $closestThiefId;
        $policemen[0]->save();
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
//        $policemen[0]->black_ticket_finished_at = $this->room->next_disclosure_at;
//        $policemen[0]->save();
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
        $goToCatching = $catchingSmallRadius > $halfWayRadius;
        $policemenObject = $this->getReorderedPoliceLocation($targetThief);
        if (1 === count($policemenObject)) {
            $targetPositions[$policemenObject[0]['playerId']] = $targetThief;
        } else {
            $halfWayPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $halfWayRadius, count($policemenObject));
            $catchingPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $catchingSmallRadius, count($policemenObject));
            $catchingEvenlySpreadPoints = $this->getPointsOnCircle($targetThief, $this->policeCenter, $catchingSmallRadius, count($policemenObject), true);
            foreach ($policemenObject as $key => $policemanObject) {
//                $distanceToThief = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $targetThief);
//                $distanceToHalfWay = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $halfWayPoints[$key]);
//                $distanceToUneven = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $catchingPoints[$key]);
//                if (!$goToCatching && $distanceToThief > $halfWayRadius && self::CLOSE_DISTANCE_DELTA < $distanceToHalfWay) {
//                    // go to half way
//                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($halfWayPoints[$key], $targetThief);
//                } elseif (self::CLOSE_DISTANCE_DELTA < $distanceToUneven) {
//                    // go to uneven catch
//                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingPoints[$key], $targetThief);
//                } else {
//                    // go to even catch
//                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$key], $targetThief);
//                }

                $targetPositions[$policemanObject['playerId']] = $targetThief;
            }
        }

//        $policemen[0]->warning_number = count($targetPositions);
//        $policemen[0]->save();

        $this->makeAStep($targetPositions);

//        $policemen[1]->warning_number = 2;
//        $policemen[1]->save();
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
//            $policeman->ping = 1;
//            $policeman->save();
            $policeman->mergeCasts(['hidden_position' => Point::class]);
            $policemanPosition = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $angle = Geometry::getAngleMadeOfPoints($this->policeCenter, $thief, $policemanPosition);
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($thief, $policemanPosition);
//            $policeman->ping = $distance * sin($angle);
//            $policeman->save();
            $newOrder[] = [
                'order' => $distance * sin($angle),
                'position' => $policemanPosition,
                'playerId' => $policeman->id,
            ];
        }

//        $policemen[0]->black_ticket_finished_at = $this->room->next_disclosure_at;
//        $policemen[0]->save();
        usort($newOrder, function ($a, $b) {
            return ($a['order'] < $b['order']) ? -1 : 1;
        });
//        $policemen[1]->black_ticket_finished_at = $this->room->next_disclosure_at;
//        $policemen[1]->save();

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

    private function makeAStep(array $targetPositions)
    {
        $positions = [];
        $botShift = 17 * $this->room->config['other']['bot_speed'] * env('BOT_REFRESH');
        /** @var Player[] $policemen */
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();

//        $policemen[0]->ping = $targetPositions[$policemen[0]->id]['x'];
//        $policemen[0]->save();
//        $policemen[1]->ping = $targetPositions[$policemen[0]->id]['y'];
//        $policemen[1]->save();

        foreach ($policemen as $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class]);
            $position = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($position, $targetPositions[$policeman->id]);
//            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($position, $targetPositions);
            $policeman->save();
            $distance = $distance > $botShift ? $botShift : $distance;
            $positionCartesian = Geometry::convertLatLngToXY($position);
            $targetCartesian = Geometry::convertLatLngToXY($targetPositions[$policeman->id]);
//            $targetCartesian = Geometry::convertLatLngToXY($targetPositions);
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
//            $position = $positions[$policeman->id];
            $position = $targetPositions[$policeman->id];
            $position = "{$position['x']} {$position['y']}";
            $policeman->hidden_position = DB::raw("ST_GeomFromText('POINT($position)')");
            $policeman->save();
        }

        $policemen[1]->warning_number = 1;
        $policemen[1]->save();
    }
}

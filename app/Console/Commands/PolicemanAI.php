<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;
use MatanYadaev\EloquentSpatial\Objects\Point;

class PolicemanAI extends Command
{
    private const CLOSE_DISTANCE_DELTA = 20;

    private const FAR_DISTANCE_DELTA = 60;

    private const CHECK_POINTS_NUMBER = 16;

    /** The name and signature of the console command.*/
    protected $signature = 'policeman-ai:start {roomId}';

    /** The console command description. */
    protected $description = 'Start the Policeman AI';

    private array $policeCenter;

    private Room $room;

    private array $thievesPositions = [];

    private array $catchingDirectionPoint;

    private $lastDisclosure;

    private bool $split = false;

    private ?array $thiefCatchingPosition = null;

    private int $caughtThieves = 0;

    private array $earlyChasePoliceCenter;

    private bool $goForward = false;

    private int $officerLId = 0;
    private int $officerRId = 0;
    private int $officerRStatus = 0;
    private int $officerLStatus = 0;

    /** Execute the console command. */
    public function handle()
    {
        $roomId = $this->argument('roomId');
        $this->room = Room::where('id', $roomId)->first();
        $this->handleSettingStartPositions();
        $this->updatePoliceCenter();
        $this->earlyChasePoliceCenter = $this->policeCenter;
        $this->catchingDirectionPoint = $this->policeCenter;
        $this->lastDisclosure = $this->room->next_disclosure_at;

        do {
            $startTime = microtime(true);
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

            if ($this->room->next_disclosure_at > $this->lastDisclosure) {
                $this->lastDisclosure = $this->room->next_disclosure_at;
                $this->clearParameters();
            }

            $this->updateThievesPosition();
            $this->updatePoliceCenter();
            if (0 < count($this->thievesPositions)) {
                $targetThiefId = $this->getNearestThief();
                $this->goToThief($this->thievesPositions[$targetThiefId]);

                $policemen[0]->warning_number = 1;
                $policemen[0]->save();
            } else {
                // search for thieves
                $this->tryToUseWhiteTicket();
            }

            $time = env('BOT_REFRESH') * 1000000 - (microtime(true) - $startTime);
            usleep($time > 0 ? $time : 0);
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
            ->whereNotNull('global_position')
            ->where(function ($query) {
                $query->where(['status' => 'CONNECTED'])
                    ->orWhere(['status' => 'DISCONNECTED']);
            })
            ->get();
        $positions = [];
        $caughtThieves = 0;
        foreach ($thieves as $thief) {
            if (null === $thief->caught_at) {
                $thief->mergeCasts(['global_position' => Point::class]);
                $thiefPosition = [
                    'x' => $thief->global_position->longitude,
                    'y' => $thief->global_position->latitude,
                ];
                $positions[$thief->id] = $thiefPosition;
            } else {
                $caughtThieves++;
            }
        }

        if ($caughtThieves > $this->caughtThieves) {
            $this->caughtThieves = $caughtThieves;
            $this->clearParameters();
        }

        $this->thievesPositions = $positions;
    }

    private function getNearestThief(): int
    {
        $closestThiefId = null;
        $closestThiefDistance = null;
        foreach ($this->thievesPositions as $playerId => $thief) {
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
        $targetPositions = [];

        $rangeRadius = $this->room->config['other']['bot_speed'] * $this->room->config['actor']['thief']['disclosure_interval'];
        $halfWayRadius = 0.5 * Geometry::getSphericalDistanceBetweenTwoPoints($this->policeCenter, $targetThief);
        $catchingRadius = 0.8 * $this->room->config['actor']['policeman']['catching']['radius'];
        $partRangeRadius = 0.4 * $rangeRadius;

        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
//        $policemen[0]->ping = $rangeRadius;
//        $policemen[0]->save();
//        $policemen[1]->ping = $halfWayRadius;
//        $policemen[1]->save();
//        $policemen[2]->ping = $catchingRadius;
//        $policemen[2]->save();

        $goToHalfWay = $catchingRadius < $halfWayRadius;
        $goToRange = $rangeRadius < $halfWayRadius * 2;
        $policemenObject = $this->getReorderedPoliceLocation($targetThief);
        $catchingLocation = $this->getCatchingLocation($policemenObject);
        if (null !== $catchingLocation) {
            $this->thiefCatchingPosition = $catchingLocation;
            $this->goForward = false;
            $targetThief = $this->thiefCatchingPosition;
        } elseif (null !== $this->thiefCatchingPosition) {
            // był łapany i uciekł -> może split even po coraz większych okręgach
            $targetThief = $this->thiefCatchingPosition;
            $this->split = true;
        }

        if (1 === count($policemenObject)) {
            $targetPositions[$policemenObject[0]['playerId']] = $targetThief;
        } else {
            $rangePoints = $this->getPointsOnCircle($targetThief, $rangeRadius, count($policemenObject));
            $rangeEvenlySpreadPoints = $this->getPointsOnCircle($targetThief, $rangeRadius, count($policemenObject), true);
            $halfWayPoints = $this->getPointsOnCircle($targetThief, $halfWayRadius, count($policemenObject));
            $catchingPoints = $this->getPointsOnCircle($targetThief, $catchingRadius, count($policemenObject));
            $catchingEvenlySpreadPoints = $this->getPointsOnCircle($targetThief, $catchingRadius, count($policemenObject), true);

            $partRangePoints = $this->getPointsOnCircle($targetThief, $partRangeRadius, count($policemenObject));
            $sphere4Points = $this->getPointsOnCircle($targetThief, 4 * $catchingRadius, count($policemenObject));
            $sphere4EvenlySpreadPoints = $this->getPointsOnCircle($targetThief, 4 * $catchingRadius, count($policemenObject), true);
            $sphere3Points = $this->getPointsOnCircle($targetThief, 3 * $catchingRadius, count($policemenObject));
            $sphere3EvenlySpreadPoints = $this->getPointsOnCircle($targetThief, 3 * $catchingRadius, count($policemenObject), true);
            $sphere2Points = $this->getPointsOnCircle($targetThief, 2 * $catchingRadius, count($policemenObject));
            $sphere2EvenlySpreadPoints = $this->getPointsOnCircle($targetThief, 2 * $catchingRadius, count($policemenObject), true);

            // 2 special
            $edgeOfficerRId = $this->getNearestPoliceman($partRangePoints[0]);
            if ($this->officerRId !== $edgeOfficerRId) {
                $this->officerRId = $edgeOfficerRId;
                $this->officerRStatus = 0;
            }
            $edgeOfficerLId = $this->getNearestPoliceman($partRangePoints[count($policemenObject) - 1], $edgeOfficerRId);
            if ($this->officerLId !== $edgeOfficerLId) {
                $this->officerLId = $edgeOfficerLId;
                $this->officerLStatus = 0;
            }

            foreach ($policemenObject as $key => $policemanObject) {
                $distanceToThief = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $targetThief);
                $distanceToHalfWay = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $halfWayPoints[$key]);
                $distanceToUneven = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $catchingPoints[$key]);

                $distanceToPartRange = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $partRangePoints[$key]);
                $distanceToSphere4 = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere4Points[$key]);
                $distanceToSphere4EvenlySpread = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere4EvenlySpreadPoints[$key]);
                $distanceToSphere3 = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere3Points[$key]);
                $distanceToSphere3EvenlySpread = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere3EvenlySpreadPoints[$key]);
                $distanceToSphere2 = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere2Points[$key]);
                $distanceToSphere2EvenlySpread = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $sphere2EvenlySpreadPoints[$key]);

                if ($distanceToThief < $this->room->config['actor']['policeman']['catching']['radius'] && !$policemanObject['isCatching']) {
                    // continue attack
                    $this->goForward = true;
                }

                // SETTING POSITIONS
                // someone is catching
                if (null !== $catchingLocation) {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingLocation, $catchingEvenlySpreadPoints[0], $targetThief);
                    continue;
                }

                // continue attack
                if ($this->goForward) {
                    $directionXY = $this->getDirectionVectorXY($this->earlyChasePoliceCenter, $targetThief);
                    $policemanPositionXY = Geometry::convertLatLngToXY($policemanObject['position']);
                    $targetDirectionXY = [
                        'x' => $policemanPositionXY['x'] + $directionXY['x'],
                        'y' => $policemanPositionXY['y'] + $directionXY['y'],
                    ];
                    $targetXY = $this->getShiftedPointXY($policemanPositionXY, $targetDirectionXY, $this->room->config['other']['bot_speed'] * env('BOT_REFRESH'));
                    $targetLatLng = Geometry::convertXYToLatLng($targetXY);
                    if ($this->isInside($targetLatLng)) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($targetLatLng, $targetThief, $targetThief);
                        continue;
                    }
                }

                //split:        s2es, s3es, s4es, r
                if ($this->split) {
                    if (2 * $catchingRadius > $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere2EvenlySpread) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere2EvenlySpreadPoints[$key], $sphere2Points[$key], $targetThief);
                    } elseif (3 * $catchingRadius > $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere3EvenlySpread) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere3EvenlySpreadPoints[$key], $sphere3Points[$key], $targetThief);
                    } elseif (4 * $catchingRadius > $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere4EvenlySpread) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere4EvenlySpreadPoints[$key], $sphere4Points[$key], $targetThief);
                    } else {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($rangeEvenlySpreadPoints[$key], $partRangePoints[$key], $targetThief);
                    }

                    continue;
                }

                // może jak są blisko (policeCenter blisko & każdy policjant blisko & może jak krótko po disclosure) to od razy catchEven

                // 2 special:   halfRange, sphere3EvenlySpread, catchingEvenlySpread
                if ($policemanObject['playerId'] === $edgeOfficerRId) {
                    $distanceToHalfRange = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $partRangePoints[0]);
                    if (0 === $this->officerRStatus && self::CLOSE_DISTANCE_DELTA < $distanceToHalfRange) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($partRangePoints[0], $sphere3EvenlySpreadPoints[0], $catchingEvenlySpreadPoints[0]);
                    } elseif (2 > $this->officerRStatus && self::CLOSE_DISTANCE_DELTA < $distanceToSphere3EvenlySpread) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere3EvenlySpreadPoints[0], $sphere2EvenlySpreadPoints[0], $catchingEvenlySpreadPoints[0]);
                        $this->officerRStatus = 1;
                    } else {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[0], $catchingPoints[0], $targetThief);
                        $this->officerRStatus = 2;
                    }

                    continue;
                } elseif ($policemanObject['playerId'] === $edgeOfficerLId) {
                    $id = count($policemenObject) - 1;
                    $distanceToHalfRange = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $partRangePoints[$id]);
                    if (0 === $this->officerLStatus && self::CLOSE_DISTANCE_DELTA < $distanceToHalfRange) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($partRangePoints[$id], $sphere3EvenlySpreadPoints[$id], $catchingEvenlySpreadPoints[$id]);
                    } elseif (2 > $this->officerLStatus && self::CLOSE_DISTANCE_DELTA < $distanceToSphere3EvenlySpread) {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere3EvenlySpreadPoints[$id], $sphere2EvenlySpreadPoints[$id], $catchingEvenlySpreadPoints[$id]);
                        $this->officerLStatus = 1;
                    } else {
                        $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$id], $catchingPoints[$id], $targetThief);
                        $this->officerLStatus = 2;
                    }

                    continue;
                }

                // depending on policeman distance: go to: partRange, s4, s3, s2, ce
                if ($partRangeRadius < $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToPartRange) {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($partRangePoints[$key], $catchingPoints[$key], $targetThief);
                } elseif (4 * $catchingRadius < $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere4) {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere4Points[$key], $catchingPoints[$key], $targetThief);
                } elseif (3 * $catchingRadius < $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere3) {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere3Points[$key], $catchingPoints[$key], $targetThief);
                } elseif (2 * $catchingRadius < $distanceToThief && self::CLOSE_DISTANCE_DELTA < $distanceToSphere2EvenlySpread) {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($sphere2EvenlySpreadPoints[$key], $catchingPoints[$key], $targetThief);
                } else {
                    $targetPositions[$policemanObject['playerId']] = $this->preventFromGoingOutside($catchingEvenlySpreadPoints[$key], $catchingPoints[$key], $targetThief);
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
                'isCatching' => $policeman->is_catching,
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
                'isCatching' => $value['isCatching'],
            ];
        }

        return $policeArray;
    }

    private function getCatchingLocation(array $policemenObject): ?array
    {
        $target = [
            'x' => 0.0,
            'y' => 0.0,
        ];
        $catchingRadius = $this->room->config['actor']['policeman']['catching']['radius'];
        $catchingLocationsXY = [];
        $catchingLocationsLatLng = [];
        foreach ($policemenObject as $key => $policemanObject) {
            if (!$policemanObject['isCatching']) {
                continue;
            }

            $nLatLng = count($catchingLocationsLatLng);
            if (!empty($catchingLocations) && 0 < $nLatLng) {
                $distance = Geometry::getSphericalDistanceBetweenTwoPoints($policemanObject['position'], $catchingLocationsLatLng[$nLatLng - 1]);
                if (2 * $catchingRadius < $distance) {
                    continue;
                }
            }

            $catchingLocationsLatLng[] = $policemanObject['position'];
            $catchingLocationsXY[] = Geometry::convertLatLngToXY($policemanObject['position']);
        }

        $nXY = count($catchingLocationsXY);
        if (0 === $nXY) {
            return null;
        }

        foreach ($catchingLocationsXY as $catchingLocation) {
            $target['x'] += $catchingLocation['x'] / $nXY;
            $target['y'] += $catchingLocation['y'] / $nXY;
        }

        return Geometry::convertXYToLatLng($target);
    }

    /** returns indexes of policemen nearest to 'edge' point */
    private function getNearestPoliceman(array $point, int $excludedId = 0): int
    {
        $policemen = $this->room
            ->players()
            ->where(['is_bot' => true])
            ->whereIn('role', ['POLICEMAN', 'PEGASUS', 'FATTY_MAN', 'EAGLE', 'AGENT'])
            ->get();
        $closestPolicemanId = null;
        $closestPolicemanDistance = null;
        foreach ($policemen as $policeman) {
            $policeman->mergeCasts(['hidden_position' => Point::class]);
            $position = [
                'x' => $policeman->hidden_position->longitude,
                'y' => $policeman->hidden_position->latitude,
            ];
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($point, $position);
            if ((null === $closestPolicemanDistance || $closestPolicemanDistance > $distance) && $policeman->id !== $excludedId) {
                $closestPolicemanDistance = $distance;
                $closestPolicemanId = $policeman->id;
            }
        }

        return $closestPolicemanId;
    }

    private function getPointsOnCircle(array $center, float $radius, int $n, bool $isEvenlySpread = false, bool $check = true, array $reference = [], $maxAngle = null): array
    {
        $points = [];
        if (empty($reference)) {
            $reference = $this->policeCenter;
        }
        if (null === $maxAngle) {
            $maxAngle = 2 * pi();
        }

        $angleDelta = $maxAngle / $n;
        if (!$isEvenlySpread) {
//            $angleDelta *= 1 - pow(1.7, -$n);
            $angleDelta *= (1 - pow(1.5, -$n - 1.25)) * $n / 2 / ($n - 1);
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
            $pointXY = $this->getShiftedPointXY($centerCartesian, $directionPoint, $radius);
            $point = Geometry::convertXYToLatLng($pointXY);
            $points[] = $point;
        }

        if ($check) {
            $points = $this->check($points, $center, $radius, $n, $isEvenlySpread);
        }

        return $points;
    }

    private function check(array $points, array $center, float $radius, int $n, bool $isEvenlySpread = false): array
    {
        $isInside = true;
        foreach ($points as $point) {
            if (!$this->isInside($point)) {
                $isInside = false;
                break;
            }
        }

        if ($isInside) {
            return $points;
        }

        $checkPoints = $this->getPointsOnCircle($center, $radius, self::CHECK_POINTS_NUMBER, true, false);
        $right = null;
        $left = null;
        foreach ($checkPoints as $key => $checkPoint) {
            if (null === $right) {
                if ($this->isInside($checkPoint)) {
                    $right = $key;
                }
            } else {
                if (!$this->isInside($checkPoint)) {
                    break;
                }

                $left = $key;
            }
        }

        $diff = $left - $right;
        $maxAngle = 2 * pi() * $diff / self::CHECK_POINTS_NUMBER;
        if (0 === $diff % 2) {
            $reference = $checkPoints[$diff / 2];
        } else {
            $point1 = $checkPoints[intval($right + $diff / 2 - 0.5)];
            $point2 = $checkPoints[intval($right + $diff / 2 + 0.5)];
            $distance = Geometry::getSphericalDistanceBetweenTwoPoints($point1, $point2);
            $reference = $this->getShiftedPointXY($point1, $point2, 0.5 * $distance);
        }

        return $this->getPointsOnCircle($center, $radius, $n, $isEvenlySpread, false, $reference, $maxAngle);
    }

    private function preventFromGoingOutside(array $target1, array $target2, array $target3 = ['x' => 0.0, 'y' => 0.0]): array
    {
        if ($this->isInside($target1)) {
            return $target1;
        } else {
            return $this->isInside($target2) ? $target2 : $target3;
        }
    }

    private function isInside(array $pointLatLng): bool
    {
        $pointXY = Geometry::convertLatLngToXY($pointLatLng);
        $point = "{$pointXY['x']} {$pointXY['y']}";
        $boundary = Geometry::convertGeometryLatLngToXY($this->room->boundary_points);
        $intersection = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($point)')) AS isInside"));
        return $intersection[0]->isInside;
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
            $newPosition = $this->getShiftedPointXY($positionCartesian, $targetCartesian, $distance);
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

    private function tryToUseWhiteTicket()
    {
        $activePegasus = null;
        /** @var Player[] $pegasuses */
        $pegasuses = $this->room
            ->players()
            ->where([
                'is_bot' => true,
                'role' => 'PEGASUS',
            ])
            ->whereNotNull('config')
            ->get();
        $number = $pegasuses[0]->config['white_ticket']['number'];
        foreach ($pegasuses as $pegasus) {
            $usedNumber = $pegasus->config['white_ticket']['used_number'];
            if ($usedNumber < $number) {
                $activePegasus = $pegasus;
                break;
            }
        }

        if (null !== $activePegasus) {
            $config =  $activePegasus->config;
            $config['white_ticket']['used_number']++;
            $activePegasus->config = $config;
            $activePegasus->save();

        }
    }

    private function clearParameters()
    {
        $this->split = false;
        $this->thiefCatchingPosition = null;
        $this->earlyChasePoliceCenter = $this->policeCenter;
        $this->goForward = false;
        $this->officerLStatus = 0;
        $this->officerRStatus = 0;
    }

    private function getShiftedPointXY(array $pointAXY, array $pointBXY, $targetDistance): array
    {
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

    private function getDirectionVectorXY(array $startPointLatLng, array $endPointLatLng): array
    {
        $startPointXY = Geometry::convertLatLngToXY($startPointLatLng);
        $endPointXY = Geometry::convertLatLngToXY($endPointLatLng);

        return [
            'x' => ($endPointXY['x'] - $startPointXY['x']),
            'y' => ($endPointXY['y'] - $startPointXY['y']),
        ];
    }
}

<?php

namespace App\Http\Libraries;

use App\Models\Room;
use Illuminate\Support\Facades\DB;

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

            $thief->hidden_position = DB::raw("ST_GeomFromText('POINT($spawnLocationLatLng)')");
            $thief->save();
        }

        return true;
    }

    private static function checkToBeWithinXY(Room $room, array $locationXY) {

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

    private static function calcArea(Room $room) {

        $boundaryLatLngString = $room->boundary_points;
        $boundaryXYString = Geometry::convertGeometryLatLngToXY($boundaryLatLngString);

        $area = DB::select(DB::raw("SELECT ST_Area(ST_GeomFromText('POLYGON(($boundaryXYString))')) AS area"));

        return $area[0]->area;
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

    private static function findExtremePointsXY(array $coordinatesXY) {

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

    private static function randLocationXY(array $extremePointsXY) {

        $x = rand(round($extremePointsXY['w']), round($extremePointsXY['e']));
        $y = rand(round($extremePointsXY['s']), round($extremePointsXY['n']));

        return [
            'x' => $x,
            'y' => $y,
        ];
    }
}

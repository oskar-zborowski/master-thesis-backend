<?php

namespace App\Http\Libraries;

use Illuminate\Support\Facades\DB;

/**
 * Klasa przechowująca wszystkie metody służące do przekształceń i wyliczeń geometrycznych
 */
class Geometry
{
    public static function simplifyBoundary(string $boundary) {

        $boundarySimplificationAccuracy = env('BOUNDARY_SIMPLIFICATION_ACCURACY');
        $simplifiedBoundary = DB::select(DB::raw("SELECT ST_AsText(ST_Simplify(ST_GeomFromText('POLYGON(($boundary))'), $boundarySimplificationAccuracy)) AS simplifiedBoundary"));

        if ($simplifiedBoundary[0]->simplifiedBoundary !== null) {
            $simplifiedBoundary = substr($simplifiedBoundary[0]->simplifiedBoundary, 9, -2);
        } else {
            $simplifiedBoundary = false;
        }

        return $simplifiedBoundary;
    }

    public static function findEquidistantPoint(array $c1, array $c2, array $c3) {

        $c1M = $c1;

        $c1 = self::convertLatLngToXY($c1);
        $c2 = self::convertLatLngToXY($c2);
        $c3 = self::convertLatLngToXY($c3);

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

                $c = self::convertXYToLatLng($c);
                $c['r'] = self::getSphericalDistanceBetweenTwoPoints($c, $c1M) - $c1M['r'];
            }
        }

        if (!isset($c)) {
            $c = false;
        }

        return $c;
    }

    public static function findSegmentMiddle(array $c1, array $c2, bool $includeRadius = false) {

        $c1M = $c1;

        $c1 = self::convertLatLngToXY($c1);
        $c2 = self::convertLatLngToXY($c2);

        if ($includeRadius) {
            $r = (self::getCartesianDistanceBetweenTwoPoints($c1, $c2) - $c1['r'] - $c2['r']) / 2;
            $c = self::getShiftedPoint($c1, $c2, $c1['r'] + $r);
            $c = self::convertXYToLatLng($c);
            $c['r'] = self::getSphericalDistanceBetweenTwoPoints($c, $c1M) - $c1M['r'];
        } else {
            $c['x'] = ($c1['x'] + $c2['x']) / 2;
            $c['y'] = ($c1['y'] + $c2['y']) / 2;
            $c = self::convertXYToLatLng($c);
            $c['r'] = self::getSphericalDistanceBetweenTwoPoints($c, $c1M);
        }

        return $c;
    }

    public static function findLinesIntersection(array $p1, array $p2, array $p3, array $p4) {

        $divisionByZero = false;

        $denominator1 = $p3['x'] - $p4['x'];
        $denominator2 = $p1['x'] - $p2['x'];

        if ($denominator1 == 0 || $denominator2 == 0) {
            $divisionByZero = true;
        } else {

            $fraction = ($p1['y'] - $p2['y']) / $denominator2 + ($p4['y'] - $p3['y']) / $denominator1;

            if ($fraction == 0) {
                $divisionByZero = true;
            } else {
                $p['x'] = ($p3['y'] - $p1['y'] + ($p4['y'] - $p3['y']) / ($p3['x'] - $p4['x']) * $p3['x'] + ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) * $p1['x']) / (($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) + ($p4['y'] - $p3['y']) / ($p3['x'] - $p4['x']));
                $p['y'] = ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) * $p['x'] + $p1['y'] + ($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) * $p1['x'];
            }
        }

        if ($divisionByZero) {
            $p = false;
        }

        return $p;
    }

    public static function getShiftedPoint(array $p1, array $p2, float $distance) {

        $p12Distance = self::getCartesianDistanceBetweenTwoPoints($p1, $p2);

        if ($p12Distance > 0) {
            $p12ShiftedPoint['x'] = $p1['x'] - ($distance * ($p1['x'] - $p2['x'])) / $p12Distance;
            $p12ShiftedPoint['y'] = $p1['y'] - ($distance * ($p1['y'] - $p2['y'])) / $p12Distance;
        } else {
            $p12ShiftedPoint['x'] = $p1['x'];
            $p12ShiftedPoint['y'] = $p1['y'];
        }

        return $p12ShiftedPoint;
    }

    public static function getCartesianDistanceBetweenTwoPoints(array $p1, array $p2) {
        return sqrt(pow($p2['x'] - $p1['x'], 2) + pow($p2['y'] - $p1['y'], 2));
    }

    public static function getSphericalDistanceBetweenTwoPoints(array $p1, array $p2) {

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
    public static function getCartesianDistanceFromPointToLine(array $p0, array $p1, array $p2) {
        return abs(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']) * $p0['x'] + $p0['y'] + ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']) * $p1['x'] - $p1['y']) / sqrt(pow(($p2['y'] - $p1['y']) / ($p1['x'] - $p2['x']), 2) + 1);
    }

    public static function convertGeometryLatLngToXY(string $geometry) {

        $convertedGeometry = '';
        $geometryPoints = explode(',', $geometry);

        foreach ($geometryPoints as $geometryPoint) {

            $geometryPoint = explode(' ', $geometryPoint);

            $p1['x'] = $geometryPoint[0];
            $p1['y'] = $geometryPoint[1];

            $p = self::convertLatLngToXY($p1);

            $convertedGeometry .= "{$p['x']} {$p['y']},";
        }

        return substr($convertedGeometry, 0, -1);
    }

    public static function convertGeometryXYToLatLng(string $geometry) {

        $convertedGeometry = '';
        $geometryPoints = explode(',', $geometry);

        foreach ($geometryPoints as $geometryPoint) {

            $geometryPoint = explode(' ', $geometryPoint);

            $p1['x'] = $geometryPoint[0];
            $p1['y'] = $geometryPoint[1];

            $p = self::convertXYToLatLng($p1);

            $convertedGeometry .= "{$p['x']} {$p['y']},";
        }

        return substr($convertedGeometry, 0, -1);
    }

    /**
     * Konwersja EPSG:4326 na EPSG:3857
     */
    public static function convertLatLngToXY(array $p1) {

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
    public static function convertXYToLatLng(array $p1) {

        $p['x'] = round($p1['x'] * 180 / 20037508.34, 5);
        $p['y'] = round(atan(exp($p1['y'] * pi() / 20037508.34)) * 360 / pi() - 90, 5);

        if (isset($p1['r'])) {
            $p['r'] = $p1['r'];
        }

        return $p;
    }
}

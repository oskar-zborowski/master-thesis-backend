<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Klasa przetwarzająca dane geometryczne
 */
class Geometry
{
    public static function geometryObject($points, string $objectType) {

        if ($objectType != 'MULTIPOINT' && $objectType != 'POLYGON') {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                env('APP_DEBUG') ? __('validation.custom.wrong-object-type') : null
            );
        }

        $result = '';
        $allPointsString = [];
        $allPointsFloat = [];

        foreach ($points as $point) {

            if ($point['lng'] === null || (!is_float($point['lng']) && !is_int($point['lng'])) ||
                $point['lat'] === null || (!is_float($point['lat']) && !is_int($point['lat'])))
            {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('validation.custom.invalid-coordinate-format')
                );
            }

            if ($result != '') {
                $result .= ',';
            }

            $pString = $point['lng'] . ' ' . $point['lat'];
            $result .= $pString;

            $allPointsString[] = $pString;
            $allPointsFloat[] = [
                'lng' => $point['lng'],
                'lat' => $point['lat'],
            ];

            if ($objectType == 'MULTIPOINT' && in_array($pString, $allPointsString)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.repeated-points')
                );
            }
        }

        if ($objectType == 'MULTIPOINT') {
            $result = DB::raw("ST_GeomFromText('$objectType($result)')");
        } else {

            $countAllPoints = count($allPointsString);

            if ($allPointsString[0] != $allPointsString[$countAllPoints-1]) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.boundary-not-closed')
                );
            }

            for ($i=0; $i<$countAllPoints-2; $i++) {
                for ($j=$i+1; $j<$countAllPoints-1; $j++) {
                    if (self::checkLineIntersection($allPointsFloat[$i], $allPointsFloat[$i+1], $allPointsFloat[$j], $allPointsFloat[($j+1) % $countAllPoints])) {
                        echo 'Nieprawidłowa granica';
                        die;
                    }
                }
            }

            $result = DB::raw("ST_GeomFromText('$objectType(($result))')");
        }

        return $result;
    }

    private static function checkLineIntersection(array $A1, array $B1, array $A2, array $B2) {

        $result = false;

        $denominator1 = $A1['lng'] - $B1['lng'];
        $denominator2 = $A2['lng'] - $B2['lng'];

        if ($denominator1 != 0) {
            $a1 = ($A1['lat'] - $B1['lat']) / $denominator1;
        }

        if ($denominator2 != 0) {
            $a2 = ($A2['lat'] - $B2['lat']) / $denominator2;
        }

        if ($denominator1 != 0 && $denominator2 != 0) {

            if ($a1 - $a2 != 0) {
                $x = ($A2['lat'] - $A1['lat'] + $a1 * $A1['lng'] - $a2 * $A2['lng']) / ($a1 - $a2);
                $y = $a1 * $x + $A1['lat'] - $a1 * $A1['lng'];
            } else {

                $y = $a1 * $B2['lng'] + $A1['lat'] - $a1 * $A1['lng'];

                if ($y == $B2['lat']) {
                    if ($A1['lng'] < $B1['lng']) {
                        if ($B2['lng'] <= $B1['lng']) {
                            $result = true;
                        }
                    } else if ($B2['lng'] >= $B1['lng']) {
                        $result = true;
                    }
                }
            }

        } else {
            if ($denominator1 != 0) {
                $x = $A2['lng'];
                $y = $a1 * $x + $A1['lat'] - $a1 * $A1['lng'];
            } else if ($denominator2 != 0) {
                $x = $A1['lng'];
                $y = $a2 * $x + $A2['lat'] - $a2 * $A2['lng'];
            } else if ($A1['lng'] == $B2['lng']) {
                if ($A1['lat'] < $B1['lat']) {
                    if ($B2['lat'] <= $B1['lat']) {
                        $result = true;
                    }
                } else if ($B2['lat'] >= $B1['lat']) {
                    $result = true;
                }
            }
        }

        if (isset($x) && isset($y)) {

            $result = true;

            if ($A1['lng'] < $B1['lng']) {
                if ($x <= $A1['lng'] || $x >= $B1['lng']) {
                    $result = false;
                }
            } else if ($x <= $B1['lng'] || $x >= $A1['lng']) {
                $result = false;
            }

            if ($A2['lng'] < $B2['lng']) {
                if ($x <= $A2['lng'] || $x >= $B2['lng']) {
                    $result = false;
                }
            } else if ($x <= $B2['lng'] || $x >= $A2['lng']) {
                $result = false;
            }

            if ($A1['lat'] < $B1['lat']) {
                if ($y <= $A1['lat'] || $y >= $B1['lat']) {
                    $result = false;
                }
            } else if ($y <= $B1['lat'] || $y >= $A1['lat']) {
                $result = false;
            }

            if ($A2['lat'] < $B2['lat']) {
                if ($y <= $A2['lat'] || $y >= $B2['lat']) {
                    $result = false;
                }
            } else if ($y <= $B2['lat'] || $y >= $A2['lat']) {
                $result = false;
            }
        }

        if ($B1['lng'] == $B2['lng'] && $B1['lat'] == $B2['lat']) {
            $result = true;
        }

        return $result;
    }
}

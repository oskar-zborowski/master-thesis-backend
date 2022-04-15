<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Models\Room;

/**
 * Klasa przetwarzająca dane geometryczne
 */
class Geometry
{
    private array $typesOfPolygons;
    private array $typesOfMultiPoints;

    private Room $room;

    private array $boundaries;
    private array $missionCenters;
    private array $monitoringCenters;
    private array $monitoringCentrals;

    public function __construct(Room $room) {
        $this->typesOfPolygons = $this->getTypesOfPolygons();
        $this->typesOfMultiPoints = $this->getTypesOfMultiPoints();
        $this->room = $room;
    }

    public function addPoints(array $points, string $objectType) {

        $this->checkPoints($points);

        if ($objectType == 'boundary') {
            $this->boundaries[] = $points;
        } else if ($objectType == 'missionCenter') {
            $this->missionCenters = array_merge($this->missionCenters, $points);
        } else if ($objectType == 'monitoringCenter') {
            $this->monitoringCenters = array_merge($this->monitoringCenters, $points);
        } else if ($objectType == 'monitoringCentral') {
            $this->monitoringCentrals = array_merge($this->monitoringCentrals, $points);
        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                env('APP_DEBUG') ? __('validation.custom.invalid-object-type') : null
            );
        }
    }

    public static function geometryObject(array $points, string $geometryObjectType) {

        // self::checkGeometryObjectType($geometryObjectType);
        // self::checkPointsFormat($points);

        // $result = '';

        // foreach ($points as $point) {

        //     if ($result != '') {
        //         $result .= ',';
        //     }

        //     $result .= $point['lng'] . ' ' . $point['lat'];
        // }

        // if (self::checkRepeatedPoints($points, $geometryObjectType)) {
        //     throw new ApiException(
        //         DefaultErrorCode::FAILED_VALIDATION(),
        //         __('validation.custom.repeated-points')
        //     );
        // }

        // if ($geometryObjectType == 'MULTIPOINT') {
        //     $result = DB::raw("ST_GeomFromText('$geometryObjectType($result)')");
        // } else if ($geometryObjectType == 'POLYGON') {

        //     if ($points[0]['lng'] != $points[$countPoints-1]['lng'] || $points[0]['lat'] != $points[$countPoints-1]['lat']) {
        //         throw new ApiException(
        //             DefaultErrorCode::FAILED_VALIDATION(),
        //             __('validation.custom.boundary-not-closed')
        //         );
        //     }

        //     for ($i=0; $i<$countAllPoints-2; $i++) {
        //         for ($j=$i+1; $j<$countAllPoints-1; $j++) {

        //             $haveCommonVertices = false;

        //             if ($allPointsFloat[$i+1]['lng'] == $allPointsFloat[$j]['lng'] && $allPointsFloat[$i+1]['lat'] == $allPointsFloat[$j]['lat'] ||
        //                 $allPointsFloat[$i]['lng'] == $allPointsFloat[$j+1]['lng'] && $allPointsFloat[$i]['lat'] == $allPointsFloat[$j+1]['lat'])
        //             {
        //                 $haveCommonVertices = true;
        //             }

        //             if (self::checkLineIntersection($allPointsFloat[$i], $allPointsFloat[$i+1], $allPointsFloat[$j], $allPointsFloat[$j+1], $haveCommonVertices)) {
        //                 echo json_encode([
        //                     $allPointsFloat[$i],
        //                     $allPointsFloat[$i+1],
        //                     $allPointsFloat[$j],
        //                     $allPointsFloat[$j+1],
        //                 ]);
        //                 die;
        //             }
        //         }
        //     }

        //     $result = DB::raw("ST_GeomFromText('$objectType(($result))')");
        // }

        // return $result;
    }

    private static function checkLineIntersection(array $A1, array $B1, array $A2, array $B2, bool $haveCommonVertices) {

        $result = false;

        $denominator1 = $A1['lng'] - $B1['lng'];
        $denominator2 = $A2['lng'] - $B2['lng'];

        if ($denominator1 != 0) {
            $a1 = ($A1['lat'] - $B1['lat']) / $denominator1;
        }

        if ($denominator2 != 0) {
            $a2 = ($A2['lat'] - $B2['lat']) / $denominator2;
        }

        if (isset($a1) && isset($a2)) {

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

            if ($haveCommonVertices) {
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
            } else {
                if ($A1['lng'] < $B1['lng']) {
                    if ($x < $A1['lng'] || $x > $B1['lng']) {
                        $result = false;
                    }
                } else if ($x < $B1['lng'] || $x > $A1['lng']) {
                    $result = false;
                }
    
                if ($A2['lng'] < $B2['lng']) {
                    if ($x < $A2['lng'] || $x > $B2['lng']) {
                        $result = false;
                    }
                } else if ($x < $B2['lng'] || $x > $A2['lng']) {
                    $result = false;
                }
    
                if ($A1['lat'] < $B1['lat']) {
                    if ($y < $A1['lat'] || $y > $B1['lat']) {
                        $result = false;
                    }
                } else if ($y < $B1['lat'] || $y > $A1['lat']) {
                    $result = false;
                }
    
                if ($A2['lat'] < $B2['lat']) {
                    if ($y < $A2['lat'] || $y > $B2['lat']) {
                        $result = false;
                    }
                } else if ($y < $B2['lat'] || $y > $A2['lat']) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    private function checkSuperimposedPoints($objectType) {

        $result = false;

        $boundaries = $this->boundaries;
        $monitorings = array_merge($this->monitoringCenters, $this->monitoringCentrals);
        $missions = $this->missionCenters;

        if (is_string($objectType)) {

            if ($objectType == 'boundary') {
                // 
            } else if ($objectType == 'monitoring') {
                // 
            } else if ($objectType == 'mission') {
                // 
            }

        } else {

            if (in_array('boundary', $objectType)) {
                // 
            }

            if (in_array('monitoring', $objectType)) {
                // 
            }

            if (in_array('mission', $objectType)) {
                // 
            }
        }
        

        // $countPoints = count($points);

        // for ($i=0; $i<$countPoints-1; $i++) {
        //     for ($j=$i+1; $j<$countPoints; $j++) {
        //         if ($objectTypes[$i] == 'boundary') {

        //             if ($objectTypes[$i] == '') {

        //             }
        //             if ($points[$i]['lng'] == $points[$j]['lng'] && $points[$i]['lat'] == $points[$j]['lat']) {
        //                 $result = true;
        //             }
        //         } else if ($geometryObjectTypes[$i] == 'POLYGON') {
        //             if ($i > 0 || $j < $countPoints-1) {
        //                 if ($points[$i]['lng'] == $points[$j]['lng'] && $points[$i]['lat'] == $points[$j]['lat']) {
        //                     $result = true;
        //                 }
        //             }
        //         }
        //     }
        // }

        // return $result;
    }

    private function checkPoints(array $points) {
        foreach ($points as $point) {
            if ($point['lng'] === null || (!is_float($point['lng']) && !is_int($point['lng'])) ||
                $point['lat'] === null || (!is_float($point['lat']) && !is_int($point['lat'])))
            {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    env('APP_DEBUG') ? __('validation.custom.invalid-coordinate-format') : null
                );
            }
        }
    }

    private function getTypesOfPolygons() {
        return [
            'boundary',
        ];
    }

    private function getTypesOfMultiPoints() {
        return [
            'missionCenter',
            'monitoringCenter',
            'monitoringCentral',
        ];
    }
}

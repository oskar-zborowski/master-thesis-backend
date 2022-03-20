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

        foreach ($points as $point) {

            if ($result != '') {
                $result .= ',';
            }

            $result .= $point['lng'] . ' ' . $point['lat'];
        }

        if ($objectType == 'MULTIPOINT') {
            $result = DB::raw("ST_GeomFromText('$objectType($result)')");
        } else {
            $result = DB::raw("ST_GeomFromText('$objectType(($result))')");
        }

        return $result;
    }
}

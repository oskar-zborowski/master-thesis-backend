<?php

namespace App\Http\Libraries\Validation;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\BaseErrorCode;
use App\Models\AccountActionType;
use App\Models\Area;
use App\Models\DefaultType;
use App\Models\DefaultTypeName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Klasa umożliwiająca przeprowadzanie procesów walidacji danych
 */
class Validation
{
    /**
     * Sprawdzenie czy dana wartość jest unikatowa w bazie danych
     * 
     * @param string $value wartość do sprawdzenia
     * @param mixed $entity encja w której będzie następowało przeszukiwanie pod kątem już występującej wartości
     * @param string $field pole po którym będzie następowało przeszukiwanie
     * 
     * @return bool
     */
    public static function checkUniqueness(string $value, $entity, string $field): bool {
        return empty($entity::where($field, $value)->first()) ? true : false;
    }

    /**
     * Sprawdzenie czy upłynął określony czas
     * 
     * @param string $timeReferencePoint punkt odniesienia względem którego liczony jest czas
     * @param int $timeMarker wartość znacznika czasu przez jak długo jest aktywny
     * @param string $comparator jeden z symboli <, >, == lub ich kombinacja, liczone względem bieżącego czasu
     * @param string $unit jednostka w jakiej wyrażony jest $timeMarker
     * 
     * @return bool
     */
    public static function timeComparison(string $timeReferencePoint, int $timeMarker, string $comparator, string $unit = 'minutes'): bool {

        $now = date('Y-m-d H:i:s');
        $expirationDate = date('Y-m-d H:i:s', strtotime('+' . $timeMarker . ' ' . $unit, strtotime($timeReferencePoint)));

        $comparasion = false;

        switch ($comparator) {

            case '==':
                if ($now == $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '>=':
                if ($now >= $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '>':
                if ($now > $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '<=':
                if ($now <= $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '<':
                if ($now < $expirationDate) {
                    $comparasion = true;
                }
                break;
        }

        return $comparasion;
    }
}

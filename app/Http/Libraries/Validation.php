<?php

namespace App\Http\Libraries;

/**
 * Klasa przeprowadzająca procesy walidacji danych
 */
class Validation
{
    public static function checkUniqueness(string $value, $entity, string $field, bool $isEncrypted = false) {

        if ($isEncrypted) {
            $aesDecrypt = Encrypter::prepareAesDecrypt($field, $value);
            $result = empty($entity::whereRaw($aesDecrypt)->first());
        } else {
            $result = empty($entity::where($field, $value)->first());
        }

        return $result;
    }

    /**
     * Sprawdzenie czy upłynął określony czas
     * 
     * @param string $timeReferencePoint punkt odniesienia względem którego liczony jest czas
     * @param int $timeMarker wartość znacznika czasu przez jak długo jest ważny
     * @param string $comparator jeden z symboli <, >, == lub ich kombinacja, liczone względem bieżącego czasu
     * @param string $unit jednostka w jakiej wyrażony jest $timeMarker
     */
    public static function timeComparison(string $timeReferencePoint, int $timeMarker, string $comparator, string $unit = 'minutes') {

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

    public static function chooseAvatar() {

        $avatars = [
            'AVATAR_1',
            'AVATAR_2',
            'AVATAR_3',
            'AVATAR_4',
            'AVATAR_5',
        ];

        $avatarCounter = count($avatars);
        $number = rand(0, $avatarCounter-1);

        return $avatars[$number];
    }
}

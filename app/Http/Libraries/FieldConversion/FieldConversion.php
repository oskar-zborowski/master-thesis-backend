<?php

namespace App\Http\Libraries\FieldConversion;

use Illuminate\Support\Str;

/**
 * Klasa umożliwiająca konwersję pomiędzy formatami CamelCase, a snake_case
 */
class FieldConversion
{
    /**
     * Konwersja nazw pól na formę camelCase
     * 
     * @param array $data dane wychodzące
     * @param int $from rząd wielkości od którego pola mają być przetwarzane dane
     * @param int $to rząd wielkości do którego pola mają być przetwarzane dane
     * 
     * @return array
     */
    public static function convertToCamelCase(array $data, int $from = 0, int $to = null): array {
        return self::convertByDefault('camel', $data, $from, $to, 0);
    }

    /**
     * Konwersja nazw pól na formę snake_case
     * 
     * @param mixed $data dane przychodzące
     * @param int $from rząd wielkości od którego pola mają być przetwarzane dane
     * @param int $to rząd wielkości do którego pola mają być przetwarzane dane
     * 
     * @return array|string|null
     */
    public static function convertToSnakeCase($data, int $from = 0, int $to = null) {
        return self::convertByDefault('snake', $data, $from, $to, 0);
    }

    /**
     * Uniwersalna konwersja nazw pól
     * 
     * @param string $conversionType informacja o typie konwersji (camel, snake)
     * @param mixed $data dane podlegające konwersji
     * @param int $from rząd wielkości od którego pola mają być przetwarzane dane
     * @param int $to rząd wielkości do którego pola mają być przetwarzane dane
     * @param int $current bieżący rząd wielkości
     * 
     * @return array|string|null
     */
    private static function convertByDefault(string $conversionType, $data, int $from = 0, int $to = null, int $current) {

        if (is_array($data) || $current > 0) {

            $fieldNames = null;

            if ($data && ($to !== null && $from <= $to || $to === null)) {
    
                if ($current == 0) {
                    $data = json_encode($data);
                    $data = json_decode($data, true);
                }
    
                foreach ($data as $key => $value) {

                    if (is_array($value)) {

                        if ($current >= $from && ($to !== null && $current <= $to || $to === null)) {
                            $fieldNames[Str::$conversionType($key)] = self::convertByDefault($conversionType, $value, $from, $to, $current+1);
                        } else if ($current < $from) {

                            $deep = self::convertByDefault($conversionType, $value, $from, $to, $current+1);

                            foreach ($deep as $k => $v) {
                                $fieldNames[Str::$conversionType($k)] = $v;
                            }
                        }

                    } else {

                        if ($current >= $from) {

                            if ($to !== null && $current <= $to || $to === null) {
                                $fieldNames[Str::$conversionType($key)] = $value;
                            } else {
                                $fieldNames = null;
                            }

                        } else {
                            $fieldNames[] = chr(27);
                        }
                    }
                }
            }

            if ($current == 0 && $fieldNames !== null) {

                $fN = null;

                foreach ($fieldNames as $key => $value) {
                    if ($value !== chr(27)) {
                        $fN[$key] = $value;
                    }
                }

                $fieldNames = $fN;
            }

        } else {
            $fieldNames = $data;
        }

        return $fieldNames;
    }
}

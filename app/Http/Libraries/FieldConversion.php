<?php

namespace App\Http\Libraries;

use Illuminate\Support\Str;

/**
 * Klasa przeprowadzająca konwersję pomiędzy formatami CamelCase, a snake_case
 */
class FieldConversion
{
    public static function convertToCamelCase($data, int $from = 0, ?int $to = null) {
        return self::convertByDefault('camel', $data, $from, $to);
    }

    public static function convertToSnakeCase($data, int $from = 0, ?int $to = null) {
        return self::convertByDefault('snake', $data, $from, $to);
    }

    private static function convertByDefault(string $conversionType, $data, int $from, ?int $to, int $current = 0) {

        if (is_array($data) || $current > 0) {

            $fieldNames = null;

            if ($data && (isset($to) && $from <= $to || !isset($to))) {
    
                if ($current == 0) {
                    $data = json_encode($data);
                    $data = json_decode($data, true);
                }
    
                foreach ($data as $key => $value) {

                    if (is_array($value)) {

                        if ($current >= $from && (isset($to) && $current <= $to || !isset($to))) {
                            $fieldNames[Str::$conversionType($key)] = self::convertByDefault($conversionType, $value, $from, $to, $current+1);
                        } else if ($current < $from) {

                            $deep = self::convertByDefault($conversionType, $value, $from, $to, $current+1);

                            foreach ($deep as $k => $v) {
                                $fieldNames[Str::$conversionType($k)] = $v;
                            }
                        }

                    } else {

                        if ($current >= $from) {

                            if (isset($to) && $current <= $to || !isset($to)) {
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

            if ($current == 0 && isset($fieldNames)) {

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

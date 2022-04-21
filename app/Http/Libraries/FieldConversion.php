<?php

namespace App\Http\Libraries;

use Illuminate\Support\Str;

/**
 * Klasa przeprowadzająca konwersję pomiędzy formatami CamelCase, a snake_case
 */
class FieldConversion
{
    public static function convertToCamelCase(array $data, int $from = 0, ?int $to = null) {
        return self::convertByDefault('camel', $data, $from, $to);
    }

    public static function convertToSnakeCase($data, int $from = 0, ?int $to = null) {
        return self::convertByDefault('snake', $data, $from, $to);
    }

    public static function stringToUppercase(string $string, bool $onlyFirstLetters = false) {

        $string = trim($string);
        $stringLength = strlen($string);
        $convertedString = self::letterToUppercase($string[0]);

        for ($i=1; $i<$stringLength; $i++) {
            if ($onlyFirstLetters) {
                if ($string[$i-1] == ' ' && (!isset($string[$i+1]) || $string[$i+1] != ' ') || $string[$i-1] == '-') {
                    $convertedString .= self::letterToUppercase($string[$i]);
                } else {
                    $convertedString .= self::letterToLowercase($string[$i]);
                }
            } else {
                $convertedString .= self::letterToUppercase($string[$i]);
            }
        }

        return $convertedString;
    }

    public static function stringToLowercase(string $string) {

        $string = trim($string);
        $stringLength = strlen($string);
        $convertedString = '';

        for ($i=0; $i<$stringLength; $i++) {
            $convertedString .= self::letterToLowercase($string[$i]);
        }

        return $convertedString;
    }

    public static function letterToUppercase(string $letter) {

        $result = $letter;
        $letterSize = strlen($letter);

        if ($letterSize == 1) {
            $result = strtoupper($letter);
        } else if ($letterSize == 2) {
            if ($letter == 'Ą' || $letter == 'ą') {
                $result = 'Ą';
            } else if ($letter == 'Ć' || $letter == 'ć') {
                $result = 'Ć';
            } else if ($letter == 'Ę' || $letter == 'ę') {
                $result = 'Ę';
            } else if ($letter == 'Ł' || $letter == 'ł') {
                $result = 'Ł';
            } else if ($letter == 'Ń' || $letter == 'ń') {
                $result = 'Ń';
            } else if ($letter == 'Ó' || $letter == 'ó') {
                $result = 'Ó';
            } else if ($letter == 'Ś' || $letter == 'ś') {
                $result = 'Ś';
            } else if ($letter == 'Ź' || $letter == 'ź') {
                $result = 'Ź';
            } else if ($letter == 'Ż' || $letter == 'ż') {
                $result = 'Ż';
            }
        }

        return $result;
    }

    public static function letterToLowercase(string $letter) {

        $result = $letter;
        $letterSize = strlen($letter);

        if ($letterSize == 1) {
            $result = strtolower($letter);
        } else if ($letterSize == 2) {
            if ($letter == 'Ą' || $letter == 'ą') {
                $result = 'ą';
            } else if ($letter == 'Ć' || $letter == 'ć') {
                $result = 'ć';
            } else if ($letter == 'Ę' || $letter == 'ę') {
                $result = 'ę';
            } else if ($letter == 'Ł' || $letter == 'ł') {
                $result = 'ł';
            } else if ($letter == 'Ń' || $letter == 'ń') {
                $result = 'ń';
            } else if ($letter == 'Ó' || $letter == 'ó') {
                $result = 'ó';
            } else if ($letter == 'Ś' || $letter == 'ś') {
                $result = 'ś';
            } else if ($letter == 'Ź' || $letter == 'ź') {
                $result = 'ź';
            } else if ($letter == 'Ż' || $letter == 'ż') {
                $result = 'ż';
            }
        }

        return $result;
    }

    private static function convertByDefault(string $conversionType, $data, int $from, ?int $to, int $current = 0) {

        if (is_array($data) || $current > 0) {

            $fieldNames = null;

            if (isset($data) && (!isset($to) || $from <= $to)) {
    
                if ($current == 0) {
                    $data = json_encode($data);
                    $data = json_decode($data, true);
                }
    
                foreach ($data as $key => $value) {

                    if (is_array($value)) {

                        if ($current >= $from && (!isset($to) || $current <= $to)) {

                            $convertedKey = Str::$conversionType($key);

                            if (ctype_upper($key[0])) {
                                $convertedKey = ucfirst($convertedKey);
                            }

                            $fieldNames[$convertedKey] = self::convertByDefault($conversionType, $value, $from, $to, $current+1);

                        } else if ($current < $from) {

                            $deep = self::convertByDefault($conversionType, $value, $from, $to, $current+1);

                            foreach ($deep as $k => $v) {

                                $convertedKey = Str::$conversionType($k);

                                if (ctype_upper($k[0])) {
                                    $convertedKey = ucfirst($convertedKey);
                                }

                                $fieldNames[$convertedKey] = $v;
                            }
                        }

                    } else {

                        if ($current >= $from) {

                            if (!isset($to) || $current <= $to) {

                                $convertedKey = Str::$conversionType($key);

                                if (ctype_upper($key[0])) {
                                    $convertedKey = ucfirst($convertedKey);
                                }

                                $fieldNames[$convertedKey] = $value;

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

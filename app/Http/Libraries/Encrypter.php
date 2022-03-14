<?php

namespace App\Http\Libraries;

use App\Http\Libraries\Validation;

/**
 * Klasa przeprowadzająca procesy szyfrowania danych
 */
class Encrypter
{
    public static function encrypt(?string $text, ?int $maxSize = null) {

        if ($text && strlen($text) > 0) {
            $iv = self::generateToken(16);
            $text = self::fillWithRandomCharacters($text, $maxSize);
            $text = openssl_encrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, $iv) . $iv;
        } else {
            $text = null;
        }

        return $text;
    }

    public static function decrypt(?string $text) {

        if ($text) {
            $iv = substr($text, -16);
            $text = substr($text, 0, -16);
            $text = openssl_decrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, $iv);
            $text = self::removeRandomCharacters($text);
        } else {
            $text = null;
        }

        return $text;
    }

    public static function generateToken(int $maxSize = 36, $entity = null, ?string $field = null, string $addition = '') {

        $maxSize -= strlen($addition);

        if ($maxSize > 0) {
            do {
                $token = self::fillWithRandomCharacters('', $maxSize, true) . $addition;
            } while ($entity && $field && !Validation::checkUniqueness($token, $entity, $field));
        } else {
            $token = null;
        }

        return $token;
    }

    public static function prepareAesDecrypt(string $field) {
        $passphrase = env('OPENSSL_PASSPHRASE');
        return "AES_DECRYPT(SUBSTRING($field, 17), $passphrase, SUBSTRING($field, 1, 16))";
    }

    private static function fillWithRandomCharacters(string $text = '', ?int $maxSize, bool $rand = false, bool $onlyCapitalLetters = false) {

        if ($maxSize) {

            $characters = 'ZmyU4RcJwPONKM38dtgG7HYVhqszTpFxovjWCELk1Qn6ufDbB2Xa9Ir0S5eAil';
            $charactersLength = strlen($characters);

            $length = $maxSize - strlen($text);

            if (!$rand) {

                $esc = chr(27);
                $temp = '';

                for ($i=0; $i<$length-1; $i++) {
                    $characterIndex = ((($i + $length) % $charactersLength) * (pow($length, 2) % $charactersLength)) % $charactersLength;
                    $temp .= $characters[$characterIndex];
                }

                if ($length) {
                    $text = $temp . $esc . $text;
                }

            } else {
                for ($i=0; $i<$length; $i++) {
                    $text .= $characters[rand(0, $charactersLength-1)];
                }

                if ($onlyCapitalLetters) {
                    $text = strtoupper($text);
                }
            }
        }

        return $text;
    }

    private static function removeRandomCharacters(string $text) {

        $length = strlen($text);

        for ($i=0; $i<$length; $i++) {
            if (ord($text[$i]) == 27) {
                break;
            }
        }

        if ($i < $length) {
            $text = substr($text, $i+1);
        }

        return $text;
    }
}

<?php

namespace App\Http\Libraries;

use App\Http\Libraries\Validation;

/**
 * Klasa przeprowadzająca procesy szyfrowania danych
 */
class Encrypter
{
    public function encrypt(?string $text, ?int $maxSize = null) {

        if ($text && strlen($text) > 0) {
            $iv = $this->generateToken(16);
            $text = $this->fillWithRandomCharacters($text, $maxSize);
            $text = openssl_encrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, $iv) . $iv;
        } else {
            $text = null;
        }

        return $text;
    }

    public function decrypt(?string $text) {

        if ($text) {
            $iv = substr($text, -16);
            $text = substr($text, 0, -16);
            $text = openssl_decrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, $iv);
            $text = $this->removeRandomCharacters($text);
        } else {
            $text = null;
        }

        return $text;
    }

    public function generateToken(int $maxSize = 36, $entity = null, ?string $field = null, string $addition = '') {

        $maxSize -= strlen($addition);

        if ($maxSize > 0) {
            do {
                $token = $this->fillWithRandomCharacters('', $maxSize, true) . $addition;
                $encryptedToken = $this->encrypt($token);
            } while ($entity && $field && !Validation::checkUniqueness($encryptedToken, $entity, $field));
        } else {
            $token = null;
        }

        return $token;
    }

    private function fillWithRandomCharacters(string $text = '', ?int $maxSize, bool $rand = false, bool $onlyCapitalLetters = false) {

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

    private function removeRandomCharacters(string $text) {

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

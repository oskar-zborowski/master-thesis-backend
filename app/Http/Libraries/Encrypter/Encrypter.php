<?php

namespace App\Http\Libraries\Encrypter;

use App\Http\Libraries\Validation\Validation;
use Illuminate\Support\Facades\Hash;

/**
 * Klasa umożliwiająca przeprowadzanie procesów szyfrowania danych
 */
class Encrypter
{
    /**
     * Zaszyfrowanie tekstu
     * 
     * @param string|null $text tekst do zaszyfrowania
     * @param int $maxSize maksymalny rozmiar pola w bazie danych
     * 
     * @return string|null
     */
    public function encrypt(?string $text, int $maxSize = null): ?string {

        if ($text !== null && strlen($text) > 0) {
            $text = $this->fillWithRandomCharacters($text, $maxSize);
            $text = openssl_encrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, env('OPENSSL_IV'));
        } else {
            $text = null;
        }

        return $text;
    }

    /**
     * Odszyfrowanie tekstu
     * 
     * @param string|null $text tekst do odszyfrowania
     * 
     * @return string|null
     */
    public function decrypt(?string $text): ?string {

        if ($text !== null && strlen($text) > 0) {
            $text = openssl_decrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, env('OPENSSL_IV'));
            $text = $this->removeRandomCharacters($text);
        } else {
            $text = null;
        }

        return $text;
    }

    /**
     * Generowanie tokenu
     * 
     * @param int $maxSize maksymalny rozmiar pola w bazie danych
     * @param mixed $entity encja w której będzie następowało przeszukiwanie pod kątem już występującego tokena
     * @param string $field pole po którym będzie następowało przeszukiwanie w bazie danych
     * @param string $addition dodatkowy tekst który ma być uwzględniony przy generowaniu tokena (dopisany na końcu), np. .jpeg
     * 
     * @return string|null
     */
    public function generateToken(int $maxSize = 64, $entity = null, string $field = 'token', string $addition = ''): ?string {

        $additionLength = strlen($addition);
        $maxSize = floor($maxSize * 0.75);
        $modulo = $maxSize % 3;
        $maxSize -= $modulo + $additionLength;

        if ($maxSize > 0) {
            do {
                $token = $this->fillWithRandomCharacters('', $maxSize, true) . $addition;
                $encryptedToken = $this->encrypt($token);
            } while ($entity && !Validation::checkUniqueness($encryptedToken, $entity, $field));
        } else {
            $token = null;
        }

        return $token;
    }

    /**
     * Wypełnienie tekstu losowymi znakami
     * 
     * @param string $text tekst do wypełnienia losowymi znakami
     * @param int $maxSize maksymalny rozmiar pola w bazie danych
     * @param bool $rand flaga określająca czy dodawane znaki mają być losowe czy według kolejności
     * 
     * @return string
     */
    private function fillWithRandomCharacters(string $text = '', int $maxSize = null, bool $rand = false): string {

        $characters = 'ZmyU4RcJwPONKM38dtgG7HYVhqszTpFxovjWCELk1Qn6ufDbB2Xa9Ir0S5eAil';
        $charactersLength = strlen($characters);

        if (!isset($maxSize)) {
            $maxSize = strlen($text);
        }

        $length = $maxSize - strlen($text);

        if ($length) {

            if (!$rand) {

                $esc = chr(27); // ESC
                $temp = '';

                for ($i=0; $i<$length-1; $i++) {
                    $characterIndex = ((($i + $length) % $charactersLength) * (pow($length, 2) % $charactersLength)) % $charactersLength;
                    $temp .= $characters[$characterIndex];
                }

                $text = $temp . $esc . $text;

            } else {
                for ($i=0; $i<$length; $i++) {
                    $text .= $characters[rand(0, $charactersLength-1)];
                }
            }
        }

        return $text;
    }

    /**
     * Usunięcie losowych znaków z tekstu
     * 
     * @param string $text tekst do odfiltrowania z losowych znaków
     * 
     * @return string
     */
    private function removeRandomCharacters(string $text): string {

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

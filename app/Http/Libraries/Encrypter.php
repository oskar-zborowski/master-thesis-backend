<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Validation;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Klasa przeprowadzająca procesy szyfrowania danych
 */
class Encrypter
{
    public static function encrypt(?string $text, ?int $maxSize = null, bool $withEncryption = true) {

        if ($text !== null && strlen($text) > 0) {

            $text = self::fillWithRandomCharacters($text, $maxSize);

            if ($withEncryption) {
                $iv = self::generateToken(16);
                $text = openssl_encrypt($text, env('OPENSSL_ALGORITHM'), env('OPENSSL_PASSPHRASE'), 0, $iv);
                $text = $iv . bin2hex(base64_decode($text));
            }

        } else {
            $text = null;
        }

        return $text;
    }

    public static function decrypt(?string $text) {

        if ($text !== null) {
            $iv = substr($text, 0, 16);
            $text = substr($text, 16);
            $text = base64_encode(hex2bin($text));
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
            } while ($entity !== null && $field !== null && !Validation::checkUniqueness($token, $entity, $field, true));
        } else {
            $token = null;
        }

        return $token;
    }

    public static function prepareAesDecrypt(string $field, string $semiEncrypted, string $search = '=') {

        $passphrase = env('OPENSSL_PASSPHRASE');

        if (strtolower($search) == 'like') {
            $semiEncrypted = "LIKE \"%$semiEncrypted%\"";
        } else if ($search == '=' || $search == '==') {
            $semiEncrypted = "= \"$semiEncrypted\"";
        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                env('APP_DEBUG') ? __('validation.custom.wrong-database-search') : null
            );
        }

        return "AES_DECRYPT(UNHEX(SUBSTRING($field, 17)), \"$passphrase\", SUBSTRING($field, 1, 16)) $semiEncrypted";
    }

    public static function generateAuthTokens() {

        $refreshToken = self::generateToken(31, PersonalAccessToken::class, 'refresh_token');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $jwt = $user->createToken('JWT');
        $jwtToken = $jwt->plainTextToken;
        $jwtId = $jwt->accessToken->getKey();

        $personalAccessToken = $user->tokenable()->where('id', $jwtId)->first();
        $personalAccessToken->refresh_token = $refreshToken;
        $personalAccessToken->save();

        Session::put('token', $jwtToken);
        Session::put('refreshToken', $refreshToken);
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

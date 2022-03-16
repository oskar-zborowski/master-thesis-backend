<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion;
use Illuminate\Support\Facades\Session;

/**
 * Klasa wysyłająca odpowiedzi zwrotne do klienta
 */
class JsonResponse
{
    public static function sendSuccess($data = null, $meta = null, int $code = 200): void {

        header('Content-Type: application/json');
        http_response_code($code);

        $response = [];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['metadata'] = $meta;
        }

        $tokens = self::getTokens();

        if ($tokens !== null) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function sendError(ErrorCode $errorCode, $data = null): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getMessage();
        }

        $response['error_code'] = $errorCode->getCode();

        if ($data !== null) {
            $response['data'] = $data;
        }

        $tokens = self::getTokens();

        if ($tokens !== null) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function getTokens() {

        $result = null;

        $token = Session::get('token');
        $refreshToken = Session::get('refreshToken');

        if ($token !== null && $refreshToken !== null) {

            Session::remove('token');
            Session::remove('refreshToken');

            $result = [
                'token' => $token,
                'refresh_token' => $refreshToken,
            ];
        }

        self::saveDeviceInforamtion();

        return $result;
    }

    public static function saveDeviceInforamtion() {
        // 
    }
}

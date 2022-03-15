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
    public static function sendSuccess($data = null, $meta = null, int $code = 200) {

        header('Content-Type: application/json');
        http_response_code($code);

        $response = [];

        if ($data) {
            $response['data'] = $data;
        }

        if ($meta) {
            $response['metadata']['general'] = $meta;
        }

        $tokens = self::getTokens();

        if ($tokens) {
            $response['metadata']['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo json_encode($response);
        die;
    }

    public static function sendError(ErrorCode $errorCode, $data = null) {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getMessage();
        }

        $response['error_code'] = $errorCode->getCode();

        if ($data) {
            $response['data'] = $data;
        }

        $tokens = self::getTokens();

        if ($tokens) {
            $response['metadata']['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo json_encode($response);
        die;
    }

    public static function getTokens() {

        $result = null;

        $token = Session::get('token');
        $refreshToken = Session::get('refreshToken');

        if ($token && $refreshToken) {
            $result = [
                'token' => $token,
                'refreshToken' => $refreshToken,
            ];
        }

        return $result;
    }
}

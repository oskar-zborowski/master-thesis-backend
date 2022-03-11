<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion;

/**
 * Klasa wysyłająca odpowiedzi zwrotne do klienta
 */
class JsonResponse
{
    public static function sendSuccess($data = null, $meta = null, int $code = 200) {

        header('Content-Type: application/json');
        http_response_code($code);

        $response = FieldConversion::convertToCamelCase([
            'data' => $data,
            'metadata' => $meta,
        ]);

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

        $response += [
            'error_code' => $errorCode->getCode(),
            'data' => $data,
        ];

        $response = FieldConversion::convertToCamelCase($response);

        echo json_encode($response);
        die;
    }
}

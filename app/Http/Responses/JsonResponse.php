<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion\FieldConversion;
use Symfony\Component\HttpFoundation\Response;

/**
 * Klasa wysyłająca odpowiedzi zwrotne do klienta
 */
class JsonResponse
{
    /**
     * Wysłanie pomyślnej odpowiedzi
     * 
     * @param mixed $data podstawowe informacje
     * @param mixed $meta dodatkowe informacje
     * 
     * @return void
     */
    public static function sendSuccess($data = null, $meta = null): void {

        header('Content-Type: application/json');
        http_response_code(Response::HTTP_OK);

        $response = FieldConversion::convertToCamelCase([
            'data' => $data,
            'metadata' => $meta,
        ]);

        echo json_encode($response);
        die;
    }

    /**
     * Wysłanie odpowiedzi z błędem
     * 
     * @param ErrorCode $errorCode obiekt kodu błędu
     * @param mixed $data podstawowe informacje
     * 
     * @return void
     */
    public static function sendError(ErrorCode $errorCode, $data = null): void {

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

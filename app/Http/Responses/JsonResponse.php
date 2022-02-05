<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion\FieldConversion;
use Symfony\Component\HttpFoundation\Response;

/**
 * Klasa obsługująca wysyłanie odpowiedzi do klienta
 */
class JsonResponse
{
    /**
     * Wysłanie pomyślnej odpowiedzi
     * 
     * @param $data podstawowe informacje zwrotne
     * @param $metadata dodatkowe informacje
     * 
     * @return void
     */
    public static function sendSuccess($data = null, $metadata = null): void {

        header('Content-Type: application/json');
        http_response_code(Response::HTTP_OK);

        $response = FieldConversion::convertToCamelCase([
            'data' => $data,
            'metadata' => $metadata
        ]);

        echo json_encode($response);
        die;
    }

    /**
     * Wysłanie odpowiedzi z błędem
     * 
     * @param ErrorCode $errorCode obiekt kodu błędu
     * @param $data podstawowe informacje zwrotne
     * @param $metadata dodatkowe informacje
     * 
     * @return void
     */
    public static function sendError(ErrorCode $errorCode, $data = null, $metadata = null): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getMessage();
        }

        $response += [
            'error_code' => $errorCode->getCode(),
            'data' => $data,
            'metadata' => $metadata
        ];

        $response = FieldConversion::convertToCamelCase($response);

        echo json_encode($response);
        die;
    }
}

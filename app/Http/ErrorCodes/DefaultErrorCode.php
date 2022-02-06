<?php

namespace App\Http\ErrorCodes;

use Symfony\Component\HttpFoundation\Response;

/**
 * Kody domyślnych odpowiedzi
 */
class DefaultErrorCode
{
    public static function INTERNAL_SERVER_ERROR(): ErrorCode {
        return new ErrorCode('DEF1', 'INTERNAL SERVER ERROR', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function FAILED_VALIDATION(): ErrorCode {
        return new ErrorCode('DEF2', 'FAILED VALIDATION', Response::HTTP_BAD_REQUEST); 
    }

    public static function PERMISSION_DENIED(): ErrorCode {
        return new ErrorCode('DEF3', 'PERMISSION DENIED', Response::HTTP_FORBIDDEN);
    }

    public static function LIMIT_EXCEEDED(): ErrorCode {
        return new ErrorCode('DEF4', 'LIMIT EXCEEDED', Response::HTTP_FORBIDDEN);
    }
}

<?php

namespace App\Http\ErrorCodes;

use Symfony\Component\HttpFoundation\Response;

/**
 * Kody domyślnych odpowiedzi
 */
class DefaultErrorCode
{
    public static function INTERNAL_SERVER_ERROR() {
        return new ErrorCode('DEF1', 'INTERNAL SERVER ERROR', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function FAILED_VALIDATION() {
        return new ErrorCode('DEF2', 'FAILED VALIDATION', Response::HTTP_BAD_REQUEST); 
    }

    public static function PERMISSION_DENIED() {
        return new ErrorCode('DEF3', 'PERMISSION DENIED', Response::HTTP_FORBIDDEN);
    }

    public static function UNAUTHORIZED() {
        return new ErrorCode('DEF4', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
    }

    public static function LIMIT_EXCEEDED() {
        return new ErrorCode('DEF5', 'LIMIT EXCEEDED', Response::HTTP_FORBIDDEN);
    }
}

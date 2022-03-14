<?php

namespace App\Http\ErrorCodes;

use Symfony\Component\HttpFoundation\Response;

/**
 * Kody domyślnych odpowiedzi
 */
class DefaultErrorCode
{
    public static function INTERNAL_SERVER_ERROR(bool $isMalicious = false) {
        return new ErrorCode('DEF1', 'INTERNAL SERVER ERROR', Response::HTTP_INTERNAL_SERVER_ERROR, $isMalicious);
    }

    public static function FAILED_VALIDATION(bool $isMalicious = false) {
        return new ErrorCode('DEF2', 'FAILED VALIDATION', Response::HTTP_BAD_REQUEST, $isMalicious); 
    }

    public static function PERMISSION_DENIED(bool $isMalicious = false) {
        return new ErrorCode('DEF3', 'PERMISSION DENIED', Response::HTTP_FORBIDDEN, $isMalicious);
    }

    public static function UNAUTHORIZED(bool $isMalicious = false) {
        return new ErrorCode('DEF4', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED, $isMalicious);
    }

    public static function LIMIT_EXCEEDED(bool $isMalicious = false) {
        return new ErrorCode('DEF5', 'LIMIT EXCEEDED', Response::HTTP_FORBIDDEN, $isMalicious);
    }
}

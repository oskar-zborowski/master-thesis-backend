<?php

namespace App\Http\ErrorCodes;

/**
 * Klasa definiująca strukturę własnych błędów
 */
class ErrorCode
{
    private string $code;
    private string $message;
    private int $httpStatus;
    private bool $isMalicious;

    public function __construct(string $code, string $message, int $httpStatus, bool $isMalicious) {
        $this->code = $code;
        $this->message = $message;
        $this->httpStatus = $httpStatus;
        $this->isMalicious = $isMalicious;
    }

    public function getCode() {
        return $this->code;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getHttpStatus() {
        return $this->httpStatus;
    }

    public function getIsMalicious() {
        return $this->isMalicious;
    }
}

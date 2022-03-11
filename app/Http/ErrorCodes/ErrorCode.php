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

    public function __construct(string $code, string $message, int $httpStatus) {
        $this->code = $code;
        $this->message = $message;
        $this->httpStatus = $httpStatus;
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
}

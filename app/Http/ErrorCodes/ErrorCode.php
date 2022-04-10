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
    private bool $logError;

    public function __construct(string $code, string $message, int $httpStatus, bool $isMalicious, bool $logError) {
        $this->code = $code;
        $this->message = $message;
        $this->httpStatus = $httpStatus;
        $this->isMalicious = $isMalicious;
        $this->logError = $logError;
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

    public function getLogError() {
        return $this->logError;
    }
}

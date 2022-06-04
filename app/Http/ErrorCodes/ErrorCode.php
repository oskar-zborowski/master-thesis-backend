<?php

namespace App\Http\ErrorCodes;

/**
 * Klasa definiująca strukturę własnych błędów
 */
class ErrorCode
{
    private string $code;
    private string $type;
    private int $httpStatus;
    private bool $isMalicious;
    private bool $isLoggingError;
    private bool $isCrawler;

    public function __construct(string $code, string $type, int $httpStatus, bool $isMalicious, bool $isLoggingError, bool $isCrawler) {
        $this->code = $code;
        $this->type = $type;
        $this->httpStatus = $httpStatus;
        $this->isMalicious = $isMalicious;
        $this->isLoggingError = $isLoggingError;
        $this->isCrawler = $isCrawler;
    }

    public function getCode() {
        return $this->code;
    }

    public function getType() {
        return $this->type;
    }

    public function getHttpStatus() {
        return $this->httpStatus;
    }

    public function getIsMalicious() {
        return $this->isMalicious;
    }

    public function getIsLoggingError() {
        return $this->isLoggingError;
    }

    public function getIsCrawler() {
        return $this->isCrawler;
    }
}

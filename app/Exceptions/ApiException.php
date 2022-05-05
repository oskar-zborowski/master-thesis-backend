<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\ErrorCode;
use Exception;

/**
 * Klasa definiująca strukturę własnych wyjątków
 */
class ApiException extends Exception
{
    private ErrorCode $errorCode;
    private $data;
    private $method;
    private $forwardMessage;

    public function __construct(ErrorCode $errorCode, $data = null, string $method, bool $forwardMessage = true) {
        parent::__construct();
        $this->errorCode = $errorCode;
        $this->data = $data;
        $this->method = $method;
        $this->forwardMessage = $forwardMessage;
    }

    public function getErrorCode() {
        return $this->errorCode;
    }

    public function getData() {
        return $this->data;
    }

    public function getMethod() {
        return $this->method;
    }

    public function getForwardMessage() {
        return $this->forwardMessage;
    }
}

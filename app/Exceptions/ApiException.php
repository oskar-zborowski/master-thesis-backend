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
    private $forwardMessage;

    public function __construct(ErrorCode $errorCode, $data = null, bool $forwardMessage = true) {
        parent::__construct();
        $this->errorCode = $errorCode;
        $this->data = $data;
        $this->forwardMessage = $forwardMessage;
    }

    public function getErrorCode() {
        return $this->errorCode;
    }

    public function getData() {
        return $this->data;
    }

    public function getForwardMessage() {
        return $this->forwardMessage;
    }
}

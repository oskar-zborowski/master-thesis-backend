<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\ErrorCode;
use Exception;

/**
 * Klasa przechowująca strukturę zwracanego wyjątku
 */
class ApiException extends Exception
{
    private ErrorCode $errorCode;
    private $data;

    /**
     * Ustawienie obiektu błędu oraz zwracanych informacji
     * 
     * @param ErrorCode $errorCode obiekt zwracanego błędu
     * @param array|string|null $data zwracane informacje
     */
    public function __construct(ErrorCode $errorCode, $data = null) {
        parent::__construct();
        $this->errorCode = $errorCode;
        $this->data = $data;
    }

    public function getErrorCode(): ErrorCode {
        return $this->errorCode;
    }

    public function getData() {
        return $this->data;
    }
}

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

    /**
     * Przypisanie obiektu błędu oraz szczegółowej informacji
     * 
     * @param ErrorCode $errorCode obiekt błędu
     * @param array|string|null $data informacja o napotkanym błędzie
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

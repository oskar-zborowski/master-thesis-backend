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

    /**
     * @param string $code unikatowy kod dla rozpoznania błędu po stronie klienta
     * @param string $message szczegółowa informacja o błędzie (widoczna wyłącznie w trybie debugowania)
     * @param int $httpStatus kod statusu HTTP
     */
    public function __construct(string $code, string $message, int $httpStatus) {
        $this->code = $code;
        $this->message = $message;
        $this->httpStatus = $httpStatus;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getMessage(): string {
        return $this->message;
    }

    public function getHttpStatus(): int {
        return $this->httpStatus;
    }
}

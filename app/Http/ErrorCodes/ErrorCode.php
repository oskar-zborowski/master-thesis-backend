<?php

namespace App\Http\ErrorCodes;

/**
 * Klasa przechowująca strukturę zwracanych błędów
 */
class ErrorCode
{
    private string $code;
    private string $message;
    private int $httpStatus;

    /**
     * Ustawienie kodu, wiadomości (widocznej tylko w trybie debugowania) oraz statusu HTTP
     * 
     * @param string $code unikatowy kod dla rozpoznania błędu po stronie klienta
     * @param string $message wiadomość z informacją o błędzie (dla trybu debugowania)
     * @param int $httpStatus kod odpowiedzi HTTP
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

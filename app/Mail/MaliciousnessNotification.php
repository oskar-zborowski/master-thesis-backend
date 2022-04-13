<?php

namespace App\Mail;

use App\Models\Connection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MaliciousnessNotification extends Mailable
{
    use Queueable, SerializesModels;

    private $message;
    private $mailSubject;

    /**
     * Create a new message instance.
     */
    public function __construct(?Connection $connection, int $status, string $errorMessage, string $errorDescription) {

        if ($connection) {

            /** @var \App\Models\User $user */
            $user = $connection->user;

            /** @var \App\Models\IpAddress $ipAddress */
            $ipAddress = $connection->ipAddress;

        } else {
            $user = null;
            $ipAddress = null;
        }

        $this->mailSubject = 'Wykryto złośliwe żądanie';

        if ($status == 0) {
            $this->mailSubject = 'Wystąpił nieoczekiwany błąd';
            $this->message = 'Wystąpił nieoczekiwany błąd!';
        } else if ($status == 1) {
            $this->message = 'Wykryto pierwszą próbę złośliwego żądania!';
        } else if ($status == 2) {
            $this->message = 'Wykryto kolejną próbę złośliwego żądania!';
        } else if ($status == 3) {

            $this->message = 'Zablokowano adres IP przychodzącego żądania';

            if ($user) {
                $this->message .= ' oraz konto użytkownika';
            }

            $this->message .= '!';

        } else if ($status == 4) {
            $this->message = 'Wymagana jest permanentna blokada adresu IP przychodzącego żądania!';
        }

        $this->message .= "<br><br>Informacje:<br>
            &emsp;Typ: $errorMessage<br>
            &emsp;Opis: $errorDescription";

        if ($connection) {
            $successfulRequestCounter = (int) $connection->successful_request_counter;
            $failedRequestCounter = (int) $connection->failed_request_counter;
            $maliciousRequestCounter = (int) $connection->malicious_request_counter;
        }

        if ($ipAddress) {
            $ipAddressBlockedAt = $ipAddress->blocked_at ? $ipAddress->blocked_at : 'brak';
        }

        if ($connection) {
            $this->message .= "<br><br>
                Połączenie:<br>
                    &emsp;ID: $connection->id<br>
                    &emsp;Pomyślnych żądań: $successfulRequestCounter<br>
                    &emsp;Błędnych żądań: $failedRequestCounter<br>
                    &emsp;Złośliwych żądań: $maliciousRequestCounter<br>
                    &emsp;Data utworzenia: $connection->created_at<br><br>
                Adres IP:<br>
                    &emsp;ID: $ipAddress->id<br>
                    &emsp;Adres IP: $ipAddress->ip_address<br>
                    &emsp;Data utworzenia: $ipAddress->created_at<br>
                    &emsp;Data blokady: $ipAddressBlockedAt";
        }

        if ($user) {

            $userBlockedAt = $user->blocked_at ? $user->blocked_at : 'brak';

            $this->message .= "<br><br>Użytkownik:<br>
                &emsp;ID: $user->id<br>
                &emsp;Nazwa: $user->name<br>
                &emsp;Data utworzenia: $user->created_at<br>
                &emsp;Data blokady: $userBlockedAt";
        }
    }

    /**
     * Build the message.
     */
    public function build() {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->to('oskarzborowski@gmail.com', 'Oskar Zborowski')
                    ->subject($this->mailSubject)
                    ->html($this->message);
    }
}

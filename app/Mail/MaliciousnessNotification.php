<?php

namespace App\Mail;

use App\Http\ErrorCodes\ErrorCode;
use App\Models\Connection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MaliciousnessNotification extends Mailable
{
    use Queueable, SerializesModels;

    private $message;

    /**
     * Create a new message instance.
     */
    public function __construct(Connection $connection, int $status, ErrorCode $errorCode, $data) {

        /** @var \App\Models\User $user */
        $user = $connection->user;

        /** @var \App\Models\IpAddress $ipAddress */
        $ipAddress = $connection->ipAddress;

        if ($status == 1) {
            $this->message = 'Wykryto pierwszą próbę złośliwego żądania!';
        } else if ($status == 2) {
            $this->message = 'Wykryto kolejną próbę złośliwego żądania!';
        } else if ($status == 3) {

            $this->message = 'Zablokowano Adres Ip przychodzącego żądania';

            if ($user !== null) {
                $this->message .= ' oraz Konto Użytkownika';
            }

            $this->message .= '!';

        } else if ($status == 4) {
            $this->message = 'Wymagana jest permanentna blokada Adresu Ip przychodzącego żądania!';
        }

        $errorType = $errorCode->getMessage();

        $this->message .= "<br><br>Informacje:<br>
            &emsp;Typ: $errorType<br>";

        if (!empty($data)) {

            if (is_array($data)) {

                if (key_exists('message', $data) || key_exists('file', $data) || key_exists('line', $data)) {

                    $errorDescription = '';

                    if (key_exists('message', $data)) {
                        if (is_array($data['message'])) {
                            $errorDescription .= implode(' ', $data['message']);
                        } else {
                            $errorDescription .= $data['message'];
                        }
                    }

                    if (key_exists('file', $data)) {

                        if (strlen($errorDescription) == 0) {
                            $errorDescription .= 'brak<br>&emsp;Plik: ';
                        } else {
                            $errorDescription .= '<br>&emsp;Plik: ';
                        }

                        if (is_array($data['file'])) {
                            $errorDescription .= implode(' ', $data['file']);
                        } else {
                            $errorDescription .= $data['file'];
                        }
                    }

                    if (key_exists('line', $data)) {

                        if (strlen($errorDescription) == 0) {
                            $errorDescription .= 'brak<br>&emsp;Linia: ';
                        } else {
                            $errorDescription .= '<br>&emsp;Linia: ';
                        }

                        if (is_array($data['line'])) {
                            $errorDescription .= implode(' ', $data['line']);
                        } else {
                            $errorDescription .= $data['line'];
                        }
                    }

                } else {
                    $errorDescription = implode(' ', $data);
                }

            } else {
                $errorDescription = $data;
            }

        } else {
            $errorDescription = 'brak';
        }

        $this->message .= "&emsp;Opis: $errorDescription<br><br>";

        $successfulRequestCounter = (int) $connection->successful_request_counter;
        $failedRequestCounter = (int) $connection->failed_request_counter;
        $maliciousRequestCounter = (int) $connection->malicious_request_counter;

        $ipAddressBlockedAt = $ipAddress->blocked_at ? $ipAddress->blocked_at : 'brak';

        $this->message .= "
            Połączenie:<br>
                &emsp;Id: $connection->id<br>
                &emsp;Pomyślnych żądań: $successfulRequestCounter<br>
                &emsp;Błędnych żądań: $failedRequestCounter<br>
                &emsp;Złośliwych żądań: $maliciousRequestCounter<br>
                &emsp;Data utworzenia: $connection->created_at<br><br>
            Adres Ip:<br>
                &emsp;Id: $ipAddress->id<br>
                &emsp;Adres Ip: $ipAddress->ip_address<br>
                &emsp;Data utworzenia: $ipAddress->created_at<br>
                &emsp;Data blokady: $ipAddressBlockedAt";

        if ($user !== null) {

            $userBlockedAt = $user->blocked_at ? $user->blocked_at : 'brak';

            $this->message .= "<br><br>Użytkownik:<br>
                &emsp;Id: $user->id<br>
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
                    ->subject('Wykryto złośliwe żądanie')
                    ->html($this->message);
    }
}

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
            $this->message = 'Wykryto pierwszą próbę złośliwego żądania!<br><br>';
        } else if ($status == 2) {
            $this->message = 'Wykryto kolejną próbę złośliwego żądania!<br><br>';
        } else if ($status == 3) {

            $this->message = 'Zablokowano Adres Ip przychodzącego żądania';

            if ($user !== null) {
                $this->message .= ' oraz Konto Użytkownika';
            }

            $this->message .= '!<br><br>';

        } else if ($status == 4) {
            $this->message = 'Wymagana jest permanentna blokada Adresu Ip przychodzącego żądania!<br><br>';
        }

        $errorType = $errorCode->getMessage();

        if (!empty($data)) {

            if (is_array($data)) {
                $errorDescription = implode(' ', $data);
            } else {
                $errorDescription = $data;
            }

        } else {
            $errorDescription = 'brak';
        }

        $this->message .= "
            Informacje:<br>
                &emsp;Typ: $errorType<br>
                &emsp;Opis: $errorDescription<br><br>";

        $successfulRequestCounter = (int) $connection->successful_request_counter;
        $failedfulRequestCounter = (int) $connection->failed_request_counter;
        $maliciousfulRequestCounter = (int) $connection->malicious_request_counter;

        $ipAddressBlockedAt = $ipAddress->blocked_at ? $ipAddress->blocked_at : 'brak';

        $this->message .= "
            Połączenie:<br>
                &emsp;Id: $connection->id<br>
                &emsp;Pomyślnych żądań: $successfulRequestCounter<br>
                &emsp;Błędnych żądań: $failedfulRequestCounter<br>
                &emsp;Złośliwych żądań: $maliciousfulRequestCounter<br>
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

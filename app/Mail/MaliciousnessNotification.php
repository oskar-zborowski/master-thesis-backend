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

    /**
     * Create a new message instance.
     */
    public function __construct(Connection $connection, int $status) {

        /** @var \App\Models\User $user */
        $user = $connection->user()->first();

        /** @var \App\Models\IpAddress $ipAddress */
        $ipAddress = $connection->ipAddress()->first();

        if ($status == 1) {
            $this->message = 'Wykryto pierwszą próbę złośliwego żądania!<br><br>';
        } else if ($status == 2) {
            $this->message = 'Wykryto kolejną próbę złośliwego żądania!<br><br>';
        } else if ($status == 3) {

            $this->message = 'Zablokowano Adres Ip przychodzącego żądania';

            if ($user) {
                $this->message .= ' oraz Konto Użytkownika';
            }

            $this->message .= '!<br><br>';

        } else if ($status == 4) {
            $this->message = 'Wymagana jest permanentna blokada Adresu Ip przychodzącego żądania!<br><br>';
        }

        $successfulRequestCounter = (int) $connection->successful_request_counter;
        $failedfulRequestCounter = (int) $connection->failed_request_counter;
        $maliciousfulRequestCounter = (int) $connection->malicious_request_counter;

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
                &emsp;Data utworzenia: $ipAddress->created_at";

        if ($user) {
            $this->message .= "<br><br>Użytkownik:<br>
                &emsp;Id: $user->id<br>
                &emsp;Nazwa: $user->name<br>
                &emsp;Data utworzenia: $user->created_at";
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

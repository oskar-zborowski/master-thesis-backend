<?php

namespace App\Mail;

use App\Http\Libraries\Log;
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
    public function __construct(?Connection $connection, int $status, string $errorType, string $errorThrower, string $errorDescription) {
        [$this->mailSubject, $this->message] = Log::prepareMessage('mail', $connection, $status, $errorType, $errorThrower, $errorDescription);
    }

    /**
     * Build the message.
     */
    public function build() {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
                    ->to(env('MAIL_TO_ADDRESS'), env('MAIL_TO_NAME'))
                    ->subject($this->mailSubject)
                    ->html($this->message);
    }
}

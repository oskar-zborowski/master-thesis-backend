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
    public function __construct(int $status, ?Connection $connection, string $errorType, string $errorThrower, string $errorFile, string $errorMethod, string $errorLine, string $errorMessage, $errorNumber) {
        [$this->mailSubject, $this->message] = Log::prepareMessage('mail', $status, $connection, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $errorNumber);
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

<?php

namespace App\Console\Commands;

use App\Http\Libraries\Log;
use App\Mail\MaliciousnessNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SaveConnectionInformation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'connection-info:save {ipAddress} {--userId=} {--isMalicious=} {--logError=} {--errorMessage=} {errorDescription} {--dbConnectionError=}';

    /**
     * The console command description.
     */
    protected $description = 'Save the connection information';

    /**
     * Execute the console command.
     */
    public function handle() {

        $ipAddress = $this->argument('ipAddress');
        $userId = $this->option('userId');
        $isMalicious = $this->option('isMalicious');
        $logError = $this->option('logError');
        $errorMessage = $this->option('errorMessage');
        $errorDescription = $this->argument('errorDescription');
        $dbConnectionError = $this->option('dbConnectionError');

        if ($dbConnectionError) {
            Mail::send(new MaliciousnessNotification(null, 0, $errorMessage, $errorDescription));
        } else {

            [$ipAddressEntity, $connection] = Log::prepareConnection($ipAddress, $userId, $isMalicious);

            if ($isMalicious) {

                if ($connection->malicious_request_counter == 1) {
                    Mail::send(new MaliciousnessNotification($connection, 1, $errorMessage, $errorDescription));
                } else if ($connection->malicious_request_counter == 2) {
                    Mail::send(new MaliciousnessNotification($connection, 2, $errorMessage, $errorDescription));
                } else if ($connection->malicious_request_counter == 3) {

                    $ipAddressEntity->blocked_at = now();
                    $ipAddressEntity->save();

                    if ($userId) {
                        /** @var \App\Models\User $user */
                        $user = $connection->user()->first();
                        $user->blocked_at = now();
                        $user->save();
                    }

                    Mail::send(new MaliciousnessNotification($connection, 3, $errorMessage, $errorDescription));

                } else if ($connection->malicious_request_counter == 50) {
                    Mail::send(new MaliciousnessNotification($connection, 4, $errorMessage, $errorDescription));
                }

            } else if ($logError) {
                Mail::send(new MaliciousnessNotification($connection, 0, $errorMessage, $errorDescription));
            }
        }

        return 0;
    }
}

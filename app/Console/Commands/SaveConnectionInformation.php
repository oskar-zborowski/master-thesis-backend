<?php

namespace App\Console\Commands;

use App\Http\Libraries\Encrypter;
use App\Mail\MaliciousnessNotification;
use App\Models\Connection;
use App\Models\IpAddress;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SaveConnectionInformation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connection-info:save {ipAddress} {--userId=} {--isMalicious=} {--errorMessage=} {errorDescription}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save the connection information';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {

        // sleep(10);

        $ipAddress = $this->argument('ipAddress');
        $userId = $this->option('userId');
        $isMalicious = $this->option('isMalicious');
        $errorMessage = $this->option('errorMessage');
        $errorDescription = $this->argument('errorDescription');

        /** @var Request $request */

        $encryptedIpAddress = Encrypter::encrypt($ipAddress, 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddressEntity */
        $ipAddressEntity = IpAddress::whereRaw($aesDecrypt)->first();

        if (!$ipAddressEntity) {
            $ipAddressEntity = new IpAddress;
            $ipAddressEntity->ip_address = $ipAddress;
            $ipAddressEntity->save();
        }

        if ($userId !== null) {
            /** @var User $user */
            $user = User::where('id', $userId)->first();
        }

        if ($userId !== null) {
            /** @var Connection $connection */
            $connection = $ipAddressEntity->connections()->where('user_id', $userId)->first();
        } else {
            /** @var Connection $connection */
            $connection = $ipAddressEntity->connections()->where('user_id', null)->first();
        }

        if (!$connection) {

            $connection = new Connection;

            if ($userId !== null) {
                $connection->user_id = $userId;
            }

            $connection->ip_address_id = $ipAddressEntity->id;

            if ($isMalicious === null) {
                $connection->successful_request_counter = 1;
            } else if ($isMalicious) {
                $connection->malicious_request_counter = 1;
            } else {
                $connection->failed_request_counter = 1;
            }

        } else {

            if ($isMalicious === null) {
                $connection->successful_request_counter = $connection->successful_request_counter + 1;
            } else if ($isMalicious) {
                $connection->malicious_request_counter = $connection->malicious_request_counter + 1;
            } else {
                $connection->failed_request_counter = $connection->failed_request_counter + 1;
            }
        }

        $connection->save();

        if ($isMalicious) {

            if ($connection->malicious_request_counter == 1) {
                Mail::send(new MaliciousnessNotification($connection, 1, $errorMessage, $errorDescription));
            } else if ($connection->malicious_request_counter == 2) {
                Mail::send(new MaliciousnessNotification($connection, 2, $errorMessage, $errorDescription));
            } else if ($connection->malicious_request_counter == 3) {

                $ipAddressEntity->blocked_at = now();
                $ipAddressEntity->save();

                if ($userId) {
                    $user->blocked_at = now();
                    $user->save();
                }

                Mail::send(new MaliciousnessNotification($connection, 3, $errorMessage, $errorDescription));

            } else if ($connection->malicious_request_counter == 50) {
                Mail::send(new MaliciousnessNotification($connection, 4, $errorMessage, $errorDescription));
            }
        }

        return 0;
    }
}

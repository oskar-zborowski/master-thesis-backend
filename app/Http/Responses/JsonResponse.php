<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\FieldConversion;
use App\Mail\MaliciousnessNotification;
use App\Models\Connection;
use App\Models\IpAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

/**
 * Klasa wysyłająca odpowiedzi zwrotne do klienta
 */
class JsonResponse
{
    public static function sendSuccess($request, $data = null, $meta = null, int $code = 200): void {

        header('Content-Type: application/json');
        http_response_code($code);

        $response = [];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['metadata'] = $meta;
        }

        $tokens = self::getTokens($request);

        if ($tokens !== null) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function sendError($request, ErrorCode $errorCode, $data = null): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getMessage();
        }

        $response['error_code'] = $errorCode->getCode();

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($errorCode->getIsMalicious()) {
            $response['data'] = __('auth.malicious-request');
        }

        $tokens = self::getTokens($request, $errorCode);

        if ($tokens !== null) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function getTokens($request, ?ErrorCode $errorCode = null) {

        $result = null;

        $token = Session::get('token');
        $refreshToken = Session::get('refreshToken');

        if ($token !== null && $refreshToken !== null) {

            Session::remove('token');
            Session::remove('refreshToken');

            $result = [
                'token' => $token,
                'refresh_token' => $refreshToken,
            ];
        }

        self::saveDeviceInformation($request, $errorCode);

        return $result;
    }

    public static function saveDeviceInformation($request, ?ErrorCode $errorCode = null) {

        /** @var Request $request */

        $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::whereRaw($aesDecrypt)->first();

        if ($ipAddress === null) {
            $ipAddress = new IpAddress;
            $ipAddress->ip_address = $request->ip();
            $ipAddress->save();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user !== null) {
            /** @var Connection $connection */
            $connection = $ipAddress->connections()->where('user_id', $user->id)->first();
        } else {
            /** @var Connection $connection */
            $connection = $ipAddress->connections()->where('user_id', null)->first();
        }

        $isMalicious = false;

        if ($connection === null) {

            $connection = new Connection;

            if ($user !== null) {
                $connection->user_id = $user->id;
            }

            $connection->ip_address_id = $ipAddress->id;

            if ($errorCode === null) {
                $connection->successful_request_counter = 1;
            } else if ($errorCode->getIsMalicious()) {
                $isMalicious = true;
                $connection->malicious_request_counter = 1;
            } else {
                $connection->failed_request_counter = 1;
            }

        } else {

            if ($errorCode === null) {
                $connection->successful_request_counter = $connection->successful_request_counter + 1;
            } else if ($errorCode->getIsMalicious()) {
                $isMalicious = true;
                $connection->malicious_request_counter = $connection->malicious_request_counter + 1;
            } else {
                $connection->failed_request_counter = $connection->failed_request_counter + 1;
            }
        }

        $connection->save();

        if ($isMalicious) {

            if ($connection->malicious_request_counter == 1) {
                Mail::send(new MaliciousnessNotification($connection, 1));
            } else if ($connection->malicious_request_counter == 5) {
                Mail::send(new MaliciousnessNotification($connection, 2));
            } else if ($connection->malicious_request_counter == 10) {

                $ipAddress->blocked_at = now();
                $ipAddress->save();

                if ($user !== null) {
                    $user->blocked_at = now();
                    $user->save();
                }

                Mail::send(new MaliciousnessNotification($connection, 3));

            } else if ($connection->malicious_request_counter == 25) {
                Mail::send(new MaliciousnessNotification($connection, 4));
            }
        }
    }
}

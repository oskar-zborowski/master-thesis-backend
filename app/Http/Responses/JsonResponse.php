<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Klasa wysyłająca odpowiedzi zwrotne do klienta
 */
class JsonResponse
{
    public static function sendSuccess($request, ?array $data = null, ?array $meta = null, int $code = 200): void {

        header('Content-Type: application/json');
        http_response_code($code);

        $response = [];

        if (isset($data)) {
            $response['data'] = $data;
        }

        if (isset($meta)) {
            $response['metadata'] = $meta;
        }

        $tokens = self::getTokens($request);

        if (isset($tokens)) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function sendError($request, ErrorCode $errorCode, ?array $data = null, bool $forwardMessage = false, bool $dbConnectionError = false): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getType();
        }

        $response['error_code'] = $errorCode->getCode();

        if (isset($data)) {
            if (env('APP_DEBUG')) {
                $response['data'] = $data;
            } else if ($forwardMessage) {
                $response['data']['message'] = $data['message'];
            }
        }

        if ($errorCode->getIsMalicious()) {
            $response['metadata'] = __('validation.custom.malicious-request');
        }

        $tokens = self::getTokens($request, $errorCode, $data, $dbConnectionError);

        if (isset($tokens)) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    private static function getTokens($request, ErrorCode $errorCode = null, $data = null, bool $dbConnectionError = false) {

        $result = null;

        $token = Session::get('token');
        $refreshToken = Session::get('refreshToken');

        if (isset($token) && isset($refreshToken)) {

            Session::remove('token');
            Session::remove('refreshToken');

            $result = [
                'token' => $token,
                'refresh_token' => $refreshToken,
            ];
        }

        self::saveConnectionInformation($request, $errorCode, $data, $dbConnectionError);

        return $result;
    }

    private static function saveConnectionInformation($request, ?ErrorCode $errorCode, $data, bool $dbConnectionError) {

        $command = "php {$_SERVER['DOCUMENT_ROOT']}/../artisan connection-info:save";

        /** @var \Illuminate\Http\Request $request */
        $command .= " \"{$request->ip()}\"";

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user) {
            $command .= " --userId=$user->id";
        }

        if (isset($errorCode)) {

            if ($errorCode->getIsMalicious()) {
                $command .= ' --isMalicious=1';
            } else {

                $command .= ' --isMalicious=0';

                if ($errorCode->getLogError()) {
                    $command .= ' --logError=1';
                }
            }

            $command .= " --errorType=\"{$errorCode->getType()}\"";
            $command .= " --errorThrower=\"{$data['thrower']}\"";
        }

        $errorDescription = '';

        if (isset($data)) {

            if (is_array($data)) {

                if (key_exists('message', $data) || key_exists('file', $data) || key_exists('line', $data) || key_exists('thrower', $data)) {

                    if (key_exists('message', $data)) {
                        if (is_array($data['message'])) {
                            $errorDescription .= implode(' ', $data['message']);
                        } else {
                            $errorDescription .= $data['message'];
                        }
                    }

                    if (strlen(trim($errorDescription)) == 0) {
                        $errorDescription .= 'brak';
                    }

                    if (key_exists('file', $data)) {

                        $errorDescription .= "\n    Plik: ";
                        $fileMemory = trim($errorDescription);

                        if (is_array($data['file'])) {
                            $errorDescription .= implode(' ', $data['file']);
                        } else {
                            $errorDescription .= $data['file'];
                        }

                        if ($fileMemory == trim($errorDescription)) {
                            $errorDescription .= 'brak';
                        }
                    }

                    if (key_exists('line', $data)) {

                        $errorDescription .= "\n    Linia: ";
                        $lineMemory = trim($errorDescription);

                        if (is_array($data['line'])) {
                            $errorDescription .= implode(' ', $data['line']);
                        } else {
                            $errorDescription .= $data['line'];
                        }

                        if ($lineMemory == trim($errorDescription)) {
                            $errorDescription .= 'brak';
                        }
                    }

                } else {
                    $errorDescription .= implode(' ', $data);
                }

            } else {
                $errorDescription .= $data;
            }
        }

        if (strlen(trim($errorDescription)) == 0) {
            $errorDescription .= 'brak';
        }

        $command .= " \"$errorDescription\"";

        if ($dbConnectionError) {
            $command .= ' 1';
        } else {
            $command .= ' 0';
        }

        $command .= ' >/dev/null 2>/dev/null &';

        shell_exec($command);
    }
}

<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion;
use Illuminate\Http\Request;
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

    public static function sendError($request, ErrorCode $errorCode, ?array $data = null, bool $attachMessage = false): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_message'] = $errorCode->getMessage();
        }

        $response['error_code'] = $errorCode->getCode();

        if ($data !== null) {
            if (env('APP_DEBUG')) {
                $response['data'] = $data;
            } else if ($attachMessage) {
                $response['data']['message'] = $data['message'];
            }
        }

        if ($errorCode->getIsMalicious()) {
            $response['metadata'] = __('validation.custom.malicious-request');
        }

        $tokens = self::getTokens($request, $errorCode, $data);

        if ($tokens !== null) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    private static function getTokens($request, ErrorCode $errorCode = null, $data = null) {

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

        self::saveConnectionInformation($request, $errorCode, $data);

        return $result;
    }

    private static function saveConnectionInformation($request, ?ErrorCode $errorCode, $data) {

        $command = "php {$_SERVER['DOCUMENT_ROOT']}/../artisan connection-info:save";

        /** @var Request $request */
        $command .= " \"{$request->ip()}\"";

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user) {
            $command .= " --userId=$user->id";
        }

        if ($errorCode !== null) {

            if ($errorCode->getIsMalicious()) {
                $command .= ' --isMalicious=1';
            } else {
                $command .= ' --isMalicious=0';
            }

            $command .= " --errorMessage=\"{$errorCode->getMessage()}\"";
        }

        if ($data !== null) {

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

        $command .= " \"$errorDescription\"";
        $command .= ' >/dev/null 2>/dev/null &';

        shell_exec($command);
    }
}

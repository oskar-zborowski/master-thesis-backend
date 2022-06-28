<?php

namespace App\Http\Responses;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\ErrorCodes\ErrorCode;
use App\Http\Libraries\FieldConversion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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

        $tokens = self::getTokens($request, $data);

        if (isset($tokens)) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    public static function sendError($request, ?array $data = null, ErrorCode $errorCode, bool $isMessageForwarded = false, bool $isDbConnectionError = false): void {

        header('Content-Type: application/json');
        http_response_code($errorCode->getHttpStatus());

        $response = [];

        if (env('APP_DEBUG')) {
            $response['error_type'] = $errorCode->getType();
        }

        $response['error_code'] = $errorCode->getCode();

        if (isset($data)) {
            if (env('APP_DEBUG')) {
                $response['data'] = FieldConversion::convertEmptyStringsToNull($data);
            } else if ($isMessageForwarded) {
                $response['data']['message'] = FieldConversion::convertEmptyStringsToNull($data['message']);
            }
        }

        if ($errorCode->getIsMalicious() || Route::currentRouteName() == 'crawler') {
            $response['metadata'] = __('validation.custom.malicious-request');
        }

        $tokens = self::getTokens($request, $data, $errorCode, $isDbConnectionError);

        if (isset($tokens)) {
            $response['tokens'] = $tokens;
        }

        $response = FieldConversion::convertToCamelCase($response);

        echo $response ? json_encode($response) : null;
        die;
    }

    private static function getTokens($request, $data, ErrorCode $errorCode = null, bool $isDbConnectionError = false) {

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

        self::saveConnectionInformation($request, $data, $errorCode, $isDbConnectionError);

        return $result;
    }

    private static function saveConnectionInformation($request, $data, ?ErrorCode $errorCode, bool $isDbConnectionError) {

        $command = "php {$_SERVER['DOCUMENT_ROOT']}/../artisan connection-info:save";

        if (env('APP_ENV') == 'local') {
            $command .= ' "83.8.175.174"';
        } else {
            /** @var \Illuminate\Http\Request $request */
            $command .= " \"{$request->ip()}\"";
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user) {
            $command .= " --userId=$user->id";
        }

        if (isset($errorCode)) {

            if ($errorCode->getIsMalicious()) {
                $command .= ' --isMalicious=1';
            }

            if ($errorCode->getIsLoggingError()) {
                $command .= ' --isLoggingError=1';
            }

            if ($errorCode->getType() == DefaultErrorCode::LIMIT_EXCEEDED()->getType()) {
                $command .= ' --isLimitExceeded=1';
            }

            if ($errorCode->getIsCrawler()) {
                $command .= ' --isCrawler=1';
            }

            $command .= " \"{$errorCode->getType()}\"";

        } else {
            $command .= ' "brak"';
        }

        if (isset($data['thrower'])) {
            $command .= " \"{$data['thrower']}\"";
        } else {
            $command .= ' "brak"';
        }

        if (isset($data['file'])) {
            $command .= " \"{$data['file']}\"";
        } else {
            $command .= ' "brak"';
        }

        if (isset($data['method'])) {
            $command .= " \"{$data['method']}\"";
        } else {
            $command .= ' "brak"';
        }

        if (isset($data['line'])) {
            $command .= " \"{$data['line']}\"";
        } else {
            $command .= ' "brak"';
        }

        if (isset($data['message'])) {

            $errorMessage = FieldConversion::multidimensionalImplode($data['message']);

            if (strlen(trim($errorMessage)) == 0) {
                $errorMessage = 'brak';
            }

            $command .= " \"$errorMessage\"";

        } else {
            $command .= ' "brak"';
        }

        if ($isDbConnectionError) {
            $command .= ' 1';
        } else {
            $command .= ' 0';
        }

        $command .= ' >/dev/null 2>/dev/null &';

        shell_exec($command);
    }
}

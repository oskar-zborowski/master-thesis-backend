<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Klasa przeprowadzająca proces uwierzytelnienia użytkownika
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function redirectTo($request) {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     * @param string[] ...$guards
     */
    public function handle($request, Closure $next, ...$guards) {

        // SET @@GLOBAL.block_encryption_mode = 'aes-256-cbc';

        if ($request->header('token') !== null && !is_string($request->header('token'))) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                __('auth.invalid-token-format'),
                __FUNCTION__
            );
        }

        if ($request->header('refreshToken') !== null && !is_string($request->header('refreshToken'))) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                __('auth.invalid-refresh-token-format'),
                __FUNCTION__
            );
        }

        $tokens[] = $request->header('token');
        $refreshTokens[] = $request->header('refreshToken');

        if ($request->token !== null && is_string($request->token)) {
            $tokens[] = $request->token;
        }

        if ($request->refreshToken !== null && is_string($request->refreshToken)) {
            $refreshTokens[] = $request->refreshToken;
        }

        $authenticationSuccess = true;

        foreach ($tokens as $token) {

            if (isset($token)) {

                try {
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    $this->authenticate($request, $guards);
                } catch (AuthenticationException $e) {
                    $authenticationSuccess = false;
                }

                if ($authenticationSuccess) {
                    break;
                }
            }
        }

        $i = 0;

        foreach ($refreshTokens as $refreshToken) {

            if (isset($refreshToken)) {

                $aesDecrypt = Encrypter::prepareAesDecrypt('refresh_token', $refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::whereRaw($aesDecrypt)->where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                ])->first();

                if ($i == 0) {
                    Session::put('personalAccessToken', $personalAccessToken);
                }

                if ($personalAccessToken && !Auth::user()) {
                    Auth::loginUsingId($personalAccessToken->tokenable_id);
                }
            }

            $i++;
        }

        return $next($request);
    }
}

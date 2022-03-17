<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Models\IpAddress;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
     * @param \Closure $next
     * @param string[] ...$guards
     */
    public function handle($request, Closure $next, ...$guards) {

        // SET @@GLOBAL.block_encryption_mode = 'aes-256-cbc';

        $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::whereRaw($aesDecrypt)->whereNotNull('blocked_at')->first();

        if ($ipAddress !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.ip-blocked')
            );
        }

        if ($request->token !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.wrong-token-format')
            );
        }

        if ($request->refresh_token !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.wrong-refresh-token-format')
            );
        }

        $token = $request->header('token');
        $refreshToken = $request->header('refreshToken');

        if ($token !== null && $refreshToken !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.double-token-given')
            );
        }

        $routeName = Route::currentRouteName();

        $routeNamesWhitelist = [
            'user-createUser',
        ];

        if ($routeName === null || !in_array($routeName, $routeNamesWhitelist)) {

            if ($token === null && $refreshToken === null) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('auth.no-token-provided')
                );
            }

            if ($token !== null) {

                try {
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    $this->authenticate($request, $guards);
                } catch (AuthenticationException $e) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(true),
                        __('auth.invalid-token')
                    );
                }

                /** @var \App\Models\User $user */
                $user = Auth::user();

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = $user->tokenable()->first();

                if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '>')) {

                    if ($personalAccessToken->expiry_alert_at === null) {

                        $personalAccessToken->expiry_alert_at = now();
                        $personalAccessToken->save();
    
                        throw new ApiException(
                            DefaultErrorCode::UNAUTHORIZED(),
                            __('auth.token-expired')
                        );

                    } else {
                        throw new ApiException(
                            DefaultErrorCode::UNAUTHORIZED(true),
                            __('auth.token-expired')
                        );
                    }
                }

            } else {

                $aesDecrypt = Encrypter::prepareAesDecrypt('refresh_token', $refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::whereRaw($aesDecrypt)->where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                ])->first();

                if ($personalAccessToken === null) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(true),
                        __('auth.invalid-refresh-token')
                    );
                }

                if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '<=')) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(true),
                        __('auth.token-still-valid')
                    );
                }

                Auth::loginUsingId($personalAccessToken->tokenable_id);
                $personalAccessToken->delete();

                $user = Auth::user();

                Encrypter::generateAuthTokens();
            }

            if ($user->blocked_at !== null) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('auth.user-blocked')
                );
            }

        } else if ($token !== null || $refreshToken !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.tokens-not-allowed')
            );
        }

        return $next($request);
    }
}

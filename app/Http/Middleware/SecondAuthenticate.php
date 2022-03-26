<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Models\IpAddress;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

class SecondAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     */
    public function handle(Request $request, Closure $next) {

        $token = $request->header('token');
        $refreshToken = $request->header('refreshToken');

        $routeName = Route::currentRouteName();

        $routeNamesWhitelist = Session::get('routeNamesWhitelist');
        Session::remove('routeNamesWhitelist');

        $personalAccessToken = Session::get('personalAccessToken');
        Session::remove('personalAccessToken');

        $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::whereRaw($aesDecrypt)->whereNotNull('blocked_at')->first();

        if ($ipAddress !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                ['message' => __('auth.ip-blocked')]
            );
        }

        if ($request->token !== null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                ['message' => __('auth.invalid-token-format')]
            );
        }

        if ($request->refresh_token !== null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                ['message' => __('auth.invalid-refresh-token-format')]
            );
        }

        if ($token !== null && $refreshToken !== null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                ['message' => __('auth.double-token-given')]
            );
        }

        if (!in_array($routeName, $routeNamesWhitelist)) {

            if ($token === null && $refreshToken === null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    ['message' => __('auth.no-token-provided')]
                );
            }

        } else if ($token !== null || $refreshToken !== null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                ['message' => __('auth.tokens-not-allowed')]
            );
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($token !== null && $user === null) {
            throw new ApiException(
                DefaultErrorCode::UNAUTHENTICATED(true),
                ['message' => __('auth.invalid-token')]
            );
        }

        if ($refreshToken !== null && $personalAccessToken === null) {
            throw new ApiException(
                DefaultErrorCode::UNAUTHENTICATED(true),
                ['message' => __('auth.invalid-refresh-token')]
            );
        }

        if ($token !== null && $user !== null) {

            /** @var PersonalAccessToken $personalAccessToken */
            $personalAccessToken = $user->tokenable()->first();

            if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '>')) {

                if ($personalAccessToken->expiry_alert_at === null) {

                    $personalAccessToken->expiry_alert_at = now();
                    $personalAccessToken->save();

                    throw new ApiException(
                        DefaultErrorCode::UNAUTHENTICATED(),
                        ['message' => __('auth.token-expired')]
                    );

                } else {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHENTICATED(true),
                        ['message' => __('auth.token-expired')]
                    );
                }
            }
        }

        if ($refreshToken !== null && $personalAccessToken !== null) {
            if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '<=')) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    ['message' => __('auth.token-still-valid')]
                );
            }
        }

        if ($user !== null && $user->blocked_at !== null) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                ['message' => __('auth.user-blocked')]
            );
        }

        if ($refreshToken !== null && $personalAccessToken !== null) {
            $personalAccessToken->delete();
            Encrypter::generateAuthTokens();
        }

        return $next($request);
    }
}
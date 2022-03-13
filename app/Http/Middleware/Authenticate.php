<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
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

        $token = $request->header('token');
        $refreshToken = $request->header('refresh-token');

        if ($token && $refreshToken) {
            throw new ApiException(
                DefaultErrorCode::UNAUTHORIZED(),
                __('auth.double-token-given')
            );
        }

        $routeName = Route::currentRouteName();

        $routeNamesWhitelist = [
            'user-createUser',
        ];

        if (!in_array($routeName, $routeNamesWhitelist)) {

            if (!$token && !$refreshToken) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHORIZED(),
                    __('auth.no-token-provided')
                );
            }

            if ($token) {

                try {
                    $this->authenticate($request, $guards);
                } catch (AuthenticationException $e) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(),
                        __('auth.invalid-token')
                    );
                }

                /** @var \App\Models\User $user */
                $user = Auth::user();

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = $user->tokenable()->first();

                if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '>')) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(),
                        __('auth.token-expired')
                    );
                }

            } else {

                $encryptedRefreshToken = Encrypter::encrypt($refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                    'refresh_token' => $encryptedRefreshToken,
                ])->first();

                if (!$personalAccessToken) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(),
                        __('auth.invalid-refresh-token')
                    );
                }

                Auth::loginUsingId($personalAccessToken->tokenable_id);
                $personalAccessToken->delete();

                // Dodanie tworzenia nowych tokenów i doczepianie ich do requesta
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            if ($user->blocked_at) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHORIZED(),
                    __('auth.user-blocked')
                );
            }

            $encryptedIpAddress = Encrypter::encrypt($request->ip());

            /** @var \App\Models\IpAddress $ipAddress */
            $ipAddress = $user->ipAddresses()->where('ip_address', $encryptedIpAddress)->first();

            if ($ipAddress->blocked_at) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHORIZED(),
                    __('auth.ip-blocked')
                );
            }

            $ipAddress->request_counter = $ipAddress->request_counter + 1;
            $ipAddress->save();

        } else if ($token || $refreshToken) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('auth.tokens-not-allowed')
            );
        }

        return $next($request);
    }
}

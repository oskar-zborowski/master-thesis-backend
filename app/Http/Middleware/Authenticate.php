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

        // TODO Trzeba inaczej dokonać sprawdzenia z uwagi na iv

        $encryptedIpAddress = Encrypter::encrypt($request->ip());

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::where('ip_address', $encryptedIpAddress)->whereNotNull('blocked_at')->first();

        if ($ipAddress) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.ip-blocked')
            );
        }

        if ($request->token || $request->refresh_token) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.wrong-token-format')
            );
        }

        $token = $request->header('token');
        $refreshToken = $request->header('refreshToken');

        if ($token && $refreshToken) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
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
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('auth.no-token-provided')
                );
            }

            if ($token) {

                try {
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

                    if (!$personalAccessToken->expiry_alert_at) {

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

                // TODO Trzeba inaczej dokonać sprawdzenia z uwagi na iv

                $encryptedRefreshToken = Encrypter::encrypt($refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                    'refresh_token' => $encryptedRefreshToken,
                ])->first();

                if (!$personalAccessToken) {
                    throw new ApiException(
                        DefaultErrorCode::UNAUTHORIZED(true),
                        __('auth.invalid-refresh-token')
                    );
                }

                Auth::loginUsingId($personalAccessToken->tokenable_id);
                $personalAccessToken->delete();

                // TODO Dobrać odpowiednią długość tokena

                $refreshToken = Encrypter::generateToken(32, PersonalAccessToken::class, 'refresh_token');
                $encryptedRefreshToken = Encrypter::encrypt($refreshToken);

                /** @var \App\Models\User $user */
                $user = Auth::user();

                $jwt = $user->createToken('JWT');
                $jwtToken = $jwt->plainTextToken;
                $jwtId = $jwt->accessToken->getKey();

                $personalAccessToken = $user->tokenable()->where('id', $jwtId)->first();
                $personalAccessToken->refresh_token = $encryptedRefreshToken;
                $personalAccessToken->save();

                $request->merge(['token' => $jwtToken]);
                $request->merge(['refresh_token' => $refreshToken]);
            }

            if ($user->blocked_at) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('auth.user-blocked')
                );
            }

        } else if ($token || $refreshToken) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.tokens-not-allowed')
            );
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Models\IpAddress;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

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

        // $user = new User;
        // $user->name = 'Oskar';
        // $user->default_avatar = 'AVATAR_1';
        // $user->app_version = '1.0.0';
        // $user->save();

        // $refreshToken = Encrypter::generateToken(31, PersonalAccessToken::class, 'refresh_token');

        // $jwt = $user->createToken('JWT');
        // $jwtToken = $jwt->plainTextToken;
        // $jwtId = $jwt->accessToken->getKey();

        // $personalAccessToken = $user->tokenable()->where('id', $jwtId)->first();
        // $personalAccessToken->refresh_token = $refreshToken;
        // $personalAccessToken->save();

        // echo json_encode([
        //     'token' => $jwtToken,
        //     'refresh_token' => $refreshToken,
        // ]);

        // die;

        $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::whereRaw($aesDecrypt)->whereNotNull('blocked_at')->first();

        if ($ipAddress) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.ip-blocked')
            );
        }

        if ($request->token || $request->refreshToken) {
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

                $aesDecrypt = Encrypter::prepareAesDecrypt('refresh_token', $refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::whereRaw($aesDecrypt)->where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                ])->first();

                if (!$personalAccessToken) {
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

                $refreshToken = Encrypter::generateToken(31, PersonalAccessToken::class, 'refresh_token');

                /** @var \App\Models\User $user */
                $user = Auth::user();

                $jwt = $user->createToken('JWT');
                $jwtToken = $jwt->plainTextToken;
                $jwtId = $jwt->accessToken->getKey();

                $personalAccessToken = $user->tokenable()->where('id', $jwtId)->first();
                $personalAccessToken->refresh_token = $refreshToken;
                $personalAccessToken->save();

                Session::put('token', $jwtToken);
                Session::put('refreshToken', $refreshToken);
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

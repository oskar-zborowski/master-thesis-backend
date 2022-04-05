<?php

namespace App\Http\Middleware;

use App\Http\Libraries\Encrypter;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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
     * @param \Closure $next
     * @param string[] ...$guards
     */
    public function handle($request, Closure $next, ...$guards) {

        // SET @@GLOBAL.block_encryption_mode = 'aes-256-cbc';

        $token = $request->header('token');
        $refreshToken = $request->header('refreshToken');

        $routeName = Route::currentRouteName();

        $routeNamesWhitelist = [
            'user-createUser',
        ];

        Session::put('routeNamesWhitelist', $routeNamesWhitelist);

        if (!in_array($routeName, $routeNamesWhitelist)) {

            if ($token !== null) {
                try {
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    $this->authenticate($request, $guards);
                } catch (AuthenticationException $e) {
                    // nic się nie dzieje
                }
            }

            if ($refreshToken !== null) {

                $aesDecrypt = Encrypter::prepareAesDecrypt('refresh_token', $refreshToken);

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = PersonalAccessToken::whereRaw($aesDecrypt)->where([
                    'tokenable_type' => 'App\Models\User',
                    'name' => 'JWT',
                ])->first();

                Session::put('personalAccessToken', $personalAccessToken);

                if ($personalAccessToken !== null && !Auth::user()) {
                    Auth::loginUsingId($personalAccessToken->tokenable_id);
                }
            }
        }

        return $next($request);
    }
}

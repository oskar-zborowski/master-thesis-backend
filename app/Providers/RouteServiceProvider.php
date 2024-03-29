<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     * This is used by Laravel authentication to redirect users after login.
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting() {

        RateLimiter::for('web', function (Request $request) {
            return Limit::perHour(env('WEB_RATE_LIMIT_PER_HOUR'))->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(env('API_USER_RATE_LIMIT_PER_MINUTE'))->by($request->user()->id)
                : Limit::perMinute(env('API_IP_RATE_LIMIT_PER_MINUTE'))->by($request->ip());
        });

        RateLimiter::for('githubLimit', function (Request $request) {
            return Limit::perMinute(env('GITHUB_RATE_LIMIT_PER_MINUTE'))->by($request->ip());
        });

        RateLimiter::for('creatingRoomLimit', function (Request $request) {
            return Limit::perMinute(env('CREATING_ROOM_RATE_LIMIT_PER_MINUTE'))->by($request->user()->id);
        });
    }
}

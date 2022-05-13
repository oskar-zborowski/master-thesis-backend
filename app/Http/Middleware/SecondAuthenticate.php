<?php

namespace App\Http\Middleware;

use App\Http\Libraries\Validation;
use Closure;
use Illuminate\Http\Request;

class SecondAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     */
    public function handle(Request $request, Closure $next) {
        Validation::secondAuthenticate($request);
        return $next($request);
    }
}

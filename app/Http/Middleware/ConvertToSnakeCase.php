<?php

namespace App\Http\Middleware;

use App\Http\Libraries\FieldConversion\FieldConversion;
use Closure;
use Illuminate\Http\Request;

/**
 * Klasa konwertująca pola na formę snake_case w przychodzącym żądaniu
 */
class ConvertToSnakeCase
{
    /**
     * @param Request $request
     * @param Closure $next
     */
    public function handle(Request $request, Closure $next) {

        $fieldNames = FieldConversion::convertToSnakeCase($request->all());

        if ($fieldNames) {
            $request->replace($fieldNames);
        }

        return $next($request);
    }
}

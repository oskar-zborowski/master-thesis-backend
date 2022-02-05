<?php

namespace App\Http\Middleware\FieldNamesConversion;

use App\Http\Libraries\FieldConversion\FieldConversion;
use Closure;
use Illuminate\Http\Request;

/**
 * Klasa obsługująca konwersję pól w przychodzącym żądaniu na formę snake_case
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

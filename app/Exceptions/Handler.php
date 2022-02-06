<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Responses\JsonResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        //
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Metoda przechwytująca wszystkie napotkane wyjątki i kierująca je w stronę klienta
     * 
     * @param \Illuminate\Http\Request $request
     * @param Throwable $throwable
     * 
     * @return void
     */
    public function render($request, Throwable $throwable): void {

        $class = get_class($throwable);

        switch ($class) {
            case ApiException::class:
                /** @var ApiException $throwable */

                JsonResponse::sendError(
                    $throwable->getErrorCode(),
                    $throwable->getData()
                );
                break;

            default:
                JsonResponse::sendError(
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                    env('APP_DEBUG') ?? $class
                );
                break;
        }
    }
}

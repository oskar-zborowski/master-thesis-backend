<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Responses\JsonResponse;
use BadMethodCallException;
use ErrorException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
     */
    public function register() {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Metoda przechwytująca wszystkie napotkane wyjątki i kierująca je w stronę klienta
     */
    public function render($request, Throwable $throwable) {

        $class = get_class($throwable);

        switch ($class) {
            case ApiException::class:
                /** @var ApiException $throwable */

                JsonResponse::sendError(
                    $throwable->getErrorCode(),
                    $throwable->getData()
                );
                break;

            case AuthenticationException::class:
                /** @var AuthenticationException $throwable */

                JsonResponse::sendError(
                    DefaultErrorCode::UNAUTHORIZED()
                );
                break;

            case BadMethodCallException::class:
            case MethodNotAllowedHttpException::class:
            case NotFoundHttpException::class:
                JsonResponse::sendError(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    env('APP_DEBUG') ? $throwable->getMessage() : null
                );
                break;

             case ErrorException::class:
                /** @var ErrorException $throwable */

                JsonResponse::sendError(
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                    env('APP_DEBUG') ? $throwable->getMessage() : null
                );
                break;

            case ThrottleRequestsException::class:
                JsonResponse::sendError(
                    DefaultErrorCode::LIMIT_EXCEEDED()
                );
                break;

            case ValidationException::class:
                /** @var ValidationException $throwable */

                JsonResponse::sendError(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    $throwable->errors()
                );
                break;

            default:
                JsonResponse::sendError(
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(),
                    env('APP_DEBUG') ? $class : null
                );
                break;
        }
    }
}

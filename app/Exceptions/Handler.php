<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Responses\JsonResponse;
use ArgumentCountError;
use BadMethodCallException;
use ErrorException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
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
                    $request,
                    $throwable->getErrorCode(),
                    env('APP_DEBUG') ? [
                        'message' => $throwable->getData(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ] : ['message' => $throwable->getData()]
                );
                break;

            case ArgumentCountError::class:
            case ErrorException::class:
            case QueryException::class:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(true),
                    env('APP_DEBUG') ? [
                        'message' => $throwable->getMessage(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ] : null
                );
                break;

            case AuthenticationException::class:
                /** @var AuthenticationException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::UNAUTHENTICATED(true),
                );
                break;

            case BadMethodCallException::class:
            case MethodNotAllowedHttpException::class:
            case ModelNotFoundException::class:
            case NotFoundHttpException::class:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    env('APP_DEBUG') ? ['message' => $throwable->getMessage()] : null
                );
                break;

            case ThrottleRequestsException::class:
                /** @var ThrottleRequestsException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::LIMIT_EXCEEDED(true),
                    ['message' => __('validation.custom.limit-exceeded', ['seconds' => $throwable->getHeaders()['Retry-After']])]
                );
                break;

            case ValidationException::class:
                /** @var ValidationException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::FAILED_VALIDATION(),
                    ['message' => $throwable->errors()]
                );
                break;

            default:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(true),
                    env('APP_DEBUG') ? ['message' => $class] : null
                );
                break;
        }
    }
}

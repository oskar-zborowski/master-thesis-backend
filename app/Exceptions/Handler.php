<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Responses\JsonResponse;
use ArgumentCountError;
use BadMethodCallException;
use ErrorException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Exception\RuntimeException;
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
                    [
                        'message' => $throwable->getData(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ],
                    true
                );
                break;

            case ArgumentCountError::class:
            case ConnectException::class:
            case ErrorException::class:
            case RuntimeException::class:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                    [
                        'message' => $throwable->getMessage(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ]
                );
                break;

            case AuthenticationException::class:
                /** @var AuthenticationException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::UNAUTHENTICATED(true)
                );
                break;

            case BadMethodCallException::class:
            case MethodNotAllowedHttpException::class:
            case ModelNotFoundException::class:
            case NotFoundHttpException::class:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    ['message' => $throwable->getMessage()]
                );
                break;

            case ThrottleRequestsException::class:
                /** @var ThrottleRequestsException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::LIMIT_EXCEEDED(false, true),
                    ['message' => __('validation.custom.limit-exceeded', ['seconds' => $throwable->getHeaders()['Retry-After']])],
                    true
                );
                break;

            case QueryException::class:
                /** @var QueryException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                    [
                        'message' => $throwable->getMessage(),
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ],
                    false,
                    true
                );
                break;

            case ValidationException::class:
                /** @var ValidationException $throwable */

                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::FAILED_VALIDATION(),
                    ['message' => $throwable->errors()],
                    true
                );
                break;

            default:
                JsonResponse::sendError(
                    $request,
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                    ['message' => $class]
                );
                break;
        }
    }
}

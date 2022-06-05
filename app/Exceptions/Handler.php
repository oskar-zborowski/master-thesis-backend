<?php

namespace App\Exceptions;

use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Validation;
use App\Http\Responses\JsonResponse;
use ArgumentCountError;
use BadMethodCallException;
use ErrorException;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use ParseError;
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
        ApiException::class,
        ArgumentCountError::class,
        BadMethodCallException::class,
        ConnectException::class,
        Exception::class,
        ErrorException::class,
        MethodNotAllowedHttpException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        QueryException::class,
        ParseError::class,
        RuntimeException::class,
        ThrottleRequestsException::class,
        ValidationException::class,
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

        if ($class == ApiException::class) {

            /** @var ApiException $throwable */

            if (str_contains($throwable->getFile(), '/app/Http/Middleware/Authenticate.php')) {
                $this->checkSecondAuthenticate($request);
            }

        } else if ($class != QueryException::class) {
            $this->checkSecondAuthenticate($request);
        }

        switch ($class) {

            case ApiException::class:
                /** @var ApiException $throwable */

                JsonResponse::sendError(
                    $request,
                    [
                        'thrower' => $class,
                        'file' => $throwable->getFile(),
                        'method' => $throwable->getMethod(),
                        'line' => $throwable->getLine(),
                        'message' => $throwable->getData(),
                    ],
                    $throwable->getErrorCode(),
                    $throwable->getIsMessageForwarded()
                );
                break;

            case BadMethodCallException::class:
            case MethodNotAllowedHttpException::class:
            case ModelNotFoundException::class:
            case NotFoundHttpException::class:
                JsonResponse::sendError(
                    $request,
                    [
                        'thrower' => $class,
                        'message' => $throwable->getMessage(),
                    ],
                    DefaultErrorCode::FAILED_VALIDATION(true)
                );
                break;

            case ThrottleRequestsException::class:
                /** @var ThrottleRequestsException $throwable */

                JsonResponse::sendError(
                    $request,
                    [
                        'thrower' => $class,
                        'message' => __('validation.custom.limit-exceeded', ['seconds' => $throwable->getHeaders()['Retry-After']]),
                    ],
                    DefaultErrorCode::LIMIT_EXCEEDED(false, true),
                    true
                );
                break;

            case ValidationException::class:
                /** @var ValidationException $throwable */

                JsonResponse::sendError(
                    $request,
                    ['message' => $throwable->errors()],
                    DefaultErrorCode::FAILED_VALIDATION(),
                    true
                );
                break;

            default:
                JsonResponse::sendError(
                    $request,
                    [
                        'thrower' => $class,
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                        'message' => $throwable->getMessage(),
                    ],
                    DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                    false,
                    $class == QueryException::class
                );
                break;
        }
    }

    private function checkSecondAuthenticate($request) {

        try {
            Validation::secondAuthenticate($request, true);
        } catch (Exception $e) {

            if (get_class($e) == ApiException::class) {

                /** @var ApiException $e */

                throw new ApiException(
                    $e->getErrorCode(),
                    $e->getData(),
                    $e->getMethod()
                );
            }
        }
    }
}

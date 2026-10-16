<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->renderJsonException($e, $request);
            }
        });
    }

    /**
     * Render an exception into a JSON response.
     */
    protected function renderJsonException(Throwable $e, Request $request): JsonResponse
    {
        $status = 500;
        $message = 'An unexpected error occurred.';

        if ($e instanceof ValidationException) {
            $status = 422;
            $message = 'Validation failed.';
            $errors = $e->errors();
            
            return response()->json([
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: $this->getDefaultMessage($status);
            
            return response()->json([
                'message' => $message,
            ], $status);
        }

        // Log the full exception for debugging
        \Log::error('Unhandled exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        if (config('app.debug')) {
            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], $status);
        }

        return response()->json([
            'message' => $message,
        ], $status);
    }

    /**
     * Get a default message for common HTTP status codes.
     */
    protected function getDefaultMessage(int $status): string
    {
        return match ($status) {
            401 => 'Unauthenticated.',
            403 => 'Forbidden.',
            404 => 'Not found.',
            419 => 'Session expired. Please refresh.',
            422 => 'Validation failed.',
            429 => 'Too many requests. Please try again later.',
            500 => 'An unexpected error occurred.',
            503 => 'Service temporarily unavailable.',
            default => 'An error occurred.',
        };
    }
}
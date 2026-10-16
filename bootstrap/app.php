<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redirect unauthenticated users to login (handled by SPA)
        $middleware->redirectGuestsTo(fn () => '/login');
        
        // Add Sanctum's EnsureFrontendRequestsAreStateful for SPA cookie auth
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                $status = 500;
                $message = 'An unexpected error occurred.';

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = 'Validation failed.';
                    return response()->json([
                        'message' => $message,
                        'errors' => $e->errors(),
                    ], $status);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: match ($status) {
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
                    return response()->json([
                        'message' => $message,
                    ], $status);
                }

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
        });
    })->create();

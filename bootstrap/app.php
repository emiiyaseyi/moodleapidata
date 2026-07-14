<?php

use App\Exceptions\MoodleApiException;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is a JSON-only API with no web login route, so never redirect
        // unauthenticated requests — always fall through to a 401 response.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'log.api' => LogApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof MoodleApiException => $e->status,
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $message = $status === 500 && ! config('app.debug')
                ? 'An unexpected error occurred.'
                : $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'version' => 'v1',
                    'error_code' => $e instanceof MoodleApiException ? $e->moodleErrorCode : null,
                    'errors' => $e instanceof ValidationException ? $e->errors() : null,
                ],
            ], $status);
        });
    })->create();

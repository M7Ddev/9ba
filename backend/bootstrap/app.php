<?php

use App\Http\Middleware\RequireAccessCode;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // routes/api.php is registered explicitly (rather than via
        // `artisan install:api`) so the project stays free of Sanctum, which
        // this prototype has no use for.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every response, including error responses rendered by the
        // exception handler.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'access.code' => RequireAccessCode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Validation failures use the same { error, message } shape as every
        // other API failure, so the frontend has exactly one thing to parse.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => 'VALIDATION',
                'message' => $e->getMessage(),
                'fields' => $e->errors(),
            ], 422);
        });
    })->create();

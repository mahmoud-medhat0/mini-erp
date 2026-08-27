<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAllPermissions;
use App\Http\Middleware\EnsureAnyPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Support\Concurrency\ConcurrencyConflictException;
use App\Support\Concurrency\DuplicateOperationInProgressException;
use App\Support\Concurrency\IdempotencyConflictException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AddSecurityHeaders::class,
            SetLocale::class,
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'permission.any' => EnsureAnyPermission::class,
            'permission.all' => EnsureAllPermissions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $renderConcurrencyConflict = function (RuntimeException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return back(303)
                ->withErrors(['concurrency' => $exception->getMessage()])
                ->with('error', $exception->getMessage());
        };

        $exceptions->render(
            fn (ConcurrencyConflictException $exception, Request $request) => $renderConcurrencyConflict($exception, $request),
        );
        $exceptions->render(
            fn (DuplicateOperationInProgressException $exception, Request $request) => $renderConcurrencyConflict($exception, $request),
        );
        $exceptions->render(
            fn (IdempotencyConflictException $exception, Request $request) => $renderConcurrencyConflict($exception, $request),
        );
    })->create();

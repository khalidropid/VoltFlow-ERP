<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureStationAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            'station.access' => EnsureStationAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\\App\\Services\\Collections\\CollectionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();

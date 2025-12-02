<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {        // Set default Filament timezone for every request (fixes Octane state persistence)
        $middleware->append(App\Http\Middleware\SetFilamentTimezone::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

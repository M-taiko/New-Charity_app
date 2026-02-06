<?php

// Register mbstring polyfill if extension is not available
if (!extension_loaded('mbstring')) {
    require_once __DIR__ . '/../app/Helpers/MbstringPolyfill.php';
    \App\Helpers\MbstringPolyfill::register();
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

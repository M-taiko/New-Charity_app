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
        $middleware->web(append: [
            \App\Http\Middleware\TrackLastPageUrl::class,
            \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $status = $e->getStatusCode();
                $viewPath = resource_path("views/errors/{$status}.blade.php");

                // Check if custom error page exists
                if (in_array($status, [403, 404, 419, 500]) && file_exists($viewPath)) {
                    try {
                        return response()->view("errors.{$status}", [], $status);
                    } catch (\Exception $viewE) {
                        // If view rendering fails, continue
                    }
                }
            }

            return null; // Let Laravel handle other exceptions
        });
    })->create();

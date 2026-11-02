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
        then: function () {
            // The SPA's versioned JSON API lives inside the web group (session auth)
            require __DIR__.'/../routes/api_v1.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\TrackLastPageUrl::class,
            \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
        // The SPA's very first request is the login POST — it has no session
        // cookie yet, so CSRF cannot apply to it (rate-limited instead).
        $middleware->validateCsrfTokens(except: ['api/v1/auth/login']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // SPA endpoints answer 401 JSON on an expired session instead of a login redirect
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['message' => 'انتهت الجلسة. يرجى تسجيل الدخول من جديد'], 401);
            }

            return null;
        });

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

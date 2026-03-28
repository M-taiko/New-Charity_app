<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastPageUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // Only track GET requests to non-API endpoints
            if ($request->isMethod('get') && !$request->is('api/*')) {
                // Store the current URL in session (excluding logout)
                if (!$request->is('logout') && !str_contains($request->path(), 'logout')) {
                    session(['last_page_url' => $request->getRequestUri()]);
                }
            }
        }

        return $next($request);
    }
}

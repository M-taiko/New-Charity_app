<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // If user is already authenticated and tries to access login page,
            // redirect them to their last page or dashboard
            if ($request->path() === 'login') {
                $lastPage = session('last_page_url', '/dashboard');
                return redirect()->to($lastPage);
            }
        }

        return $next($request);
    }
}

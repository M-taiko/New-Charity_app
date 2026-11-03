<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns Blade-era redirect + flash responses into the JSON action contract
 * required by the SPA (section 7.3 of the redesign plan):
 *   { data: <entity|null>, message, level }
 * The old controllers keep their exact business rules; only the transport
 * changes. Validation (422) and authorization (403) failures are already
 * rendered as JSON by Laravel when the client sends Accept: application/json.
 */
class ApiV1Response
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            $level = null;
            $message = null;
            foreach (['success', 'error', 'warning', 'info'] as $type) {
                if ($request->session()->has($type)) {
                    $level = $type;
                    $message = $request->session()->get($type);
                    $request->session()->forget($type);
                    break;
                }
            }

            if ($message === null) {
                $message = $response->isRedirection() && str_contains((string) $response->getTargetUrl(), 'login')
                    ? 'انتهت الجلسة. يرجى تسجيل الدخول من جديد'
                    : 'تم تنفيذ الطلب';
                $level ??= 'success';
            }

            // Machine-readable business-rule code (Task3 §13): controllers may
            // flash `code` next to the message so the SPA can translate it
            // precisely (errors.codes.<code>) instead of string-matching.
            $code = $request->session()->has('code') ? $request->session()->pull('code') : null;

            return response()->json([
                'data' => null,
                'message' => $message,
                'level' => $level,
                ...(is_string($code) && $code !== '' ? ['code' => $code] : []),
            ], $level === 'error' ? 422 : 200);
        }

        return $response;
    }
}

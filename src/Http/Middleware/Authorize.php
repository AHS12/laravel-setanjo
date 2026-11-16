<?php

namespace Ahs12\Setanjo\Http\Middleware;

use Ahs12\Setanjo\Setanjo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may open the dashboard: Setanjo::auth() when set, else the local environment,
 * else the `viewSetanjo` gate.
 *
 * It is listed in `setanjo.dashboard.middleware` (like Telescope's and Pulse's). Keep it to
 * use the gate, or remove it and let your own middleware decide, e.g.
 * ['web', 'auth', 'role:admin'].
 */
class Authorize
{
    /** Request attribute set once this check has passed (read by GuardDashboard). */
    public const PASSED = 'setanjo.authorized';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Setanjo::check($request)) {
            return static::deny($request, __('setanjo::dashboard.forbidden'));
        }

        $request->attributes->set(self::PASSED, true);

        return $next($request);
    }

    public static function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}

<?php

namespace Ahs12\Setanjo\Http\Middleware;

use Ahs12\Setanjo\Setanjo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the dashboard: viewing needs Setanjo::check(), any write needs Setanjo::canManage().
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setanjo::check($request)) {
            return $this->deny($request, __('setanjo::dashboard.forbidden'));
        }

        if (! $request->isMethodSafe() && ! Setanjo::canManage($request)) {
            return $this->deny($request, __('setanjo::dashboard.readonly'));
        }

        return $next($request);
    }

    protected function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}

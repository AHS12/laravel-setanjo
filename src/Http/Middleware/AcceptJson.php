<?php

namespace Ahs12\Setanjo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First on the public API: every answer is JSON (errors, 401/403, validation), whatever the
 * client sent as Accept, so API clients never get an HTML error page or a login redirect.
 */
class AcceptJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}

<?php

namespace Ahs12\Setanjo\Http\Middleware;

use Ahs12\Setanjo\Setanjo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Always added after `setanjo.dashboard.middleware`, so it cannot be configured away:
 *
 * 1. Outside the local environment, when Authorize is not in the stack, someone must be
 *    signed in (`setanjo.dashboard.require_auth`), so a stack of just ['web'] is never public.
 * 2. Writes respect readonly mode and Setanjo::manage() / the `manageSetanjo` gate.
 */
class GuardDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->attributes->get(Authorize::PASSED)
            && config('setanjo.dashboard.require_auth', true)
            && ! app()->environment('local')
            && $request->user() === null) {
            return Authorize::deny($request, __('setanjo::dashboard.unprotected'));
        }

        if (! $request->isMethodSafe() && ! Setanjo::canManage($request)) {
            return Authorize::deny($request, __('setanjo::dashboard.readonly'));
        }

        return $next($request);
    }
}

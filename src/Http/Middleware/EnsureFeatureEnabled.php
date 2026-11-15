<?php

namespace Ahs12\Setanjo\Http\Middleware;

use Ahs12\Setanjo\SetanjoManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `setanjo.feature:flag_one,flag_two` (every listed flag must be enabled).
 *
 * Flags resolve for the authenticated user through the scope chain, or globally for guests.
 */
class EnsureFeatureEnabled
{
    public function __construct(protected SetanjoManager $setanjo) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();
        $subject = $user instanceof Model ? $user : null;

        foreach ($features as $feature) {
            if ($this->setanjo->feature($feature)->for($subject)->disabled()) {
                return $this->deny($request, $feature);
            }
        }

        return $next($request);
    }

    protected function deny(Request $request, string $feature): Response
    {
        $message = __('setanjo::messages.feature_disabled');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'feature' => $feature,
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}

<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    /**
     * Counts for the landing screen: settings, flags, overrides per scope type, most overridden keys.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->overview()]);
    }
}

<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Dashboard\Drift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DriftController extends Controller
{
    public function index(Drift $drift): JsonResponse
    {
        return response()->json(['data' => $drift->report()]);
    }

    /**
     * Run setanjo:sync. The dashboard previews with dry_run first.
     */
    public function sync(Request $request, Drift $drift): JsonResponse
    {
        $request->validate([
            'dry_run' => 'sometimes|boolean',
            'prune' => 'sometimes|boolean',
            'force_types' => 'sometimes|boolean',
            'reset_meta' => 'sometimes|boolean',
            'reset_groups' => 'sometimes|boolean',
        ]);

        $options = [];
        foreach (['dry_run', 'prune', 'force_types', 'reset_meta', 'reset_groups'] as $option) {
            if ($request->boolean($option)) {
                $options['--'.str_replace('_', '-', $option)] = true;
            }
        }

        $exitCode = Artisan::call('setanjo:sync', $options);

        return response()->json([
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
            'data' => $drift->report(),
        ]);
    }
}

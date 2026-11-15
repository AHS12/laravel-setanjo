<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScopesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->scopes->summary()]);
    }

    /**
     * Records of a scope type matching ?search=, or the records that already have overrides.
     */
    public function records(Request $request, string $type): JsonResponse
    {
        $search = $request->query('search');

        if ($search === null || $search === '') {
            return response()->json(['data' => $this->catalog->overriddenRecords($type), 'overridden' => true]);
        }

        return response()->json(['data' => $this->scopes->search($type, (string) $search), 'overridden' => false]);
    }

    /**
     * Every setting and flag as one record sees it.
     */
    public function show(string $type, string $id): JsonResponse
    {
        return response()->json(['data' => $this->catalog->forScope($this->scopes->find($type, $id))]);
    }
}

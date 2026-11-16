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
     * Records of a scope type matching ?search=, or (paginated) the records that already have overrides.
     */
    public function records(Request $request, string $type): JsonResponse
    {
        $search = $request->query('search');

        if ($search === null || $search === '') {
            $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:200']);
            $page = $this->catalog->overriddenRecords($type, (int) $request->query('page', '1'), (int) $request->query('per_page', '50'));

            return response()->json([
                'data' => $page['data'],
                'overridden' => true,
                'meta' => array_diff_key($page, ['data' => true]),
            ]);
        }

        return response()->json(['data' => $this->scopes->search($type, (string) $search), 'overridden' => false]);
    }

    /**
     * Every setting and flag as one record sees it. A deleted record that still has
     * overrides shows only those, so they can be cleared.
     */
    public function show(string $type, string $id): JsonResponse
    {
        return response()->json(['data' => $this->catalog->forScope($this->scopeOrGhost($type, $id))]);
    }

    /**
     * Clear every setting and flag override stored for one record (deleted records included).
     */
    public function destroy(string $type, string $id): JsonResponse
    {
        $scope = $this->scopeOrGhost($type, $id);

        $this->setanjo->for($scope)->flush();

        foreach (array_keys($this->setanjo->featureRepository()->overrides($scope)) as $key) {
            $this->setanjo->feature($key)->for($scope)->toggle(null);
        }

        return response()->json(['data' => $this->catalog->forScope($scope)]);
    }
}

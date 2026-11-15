<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeaturesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->features()]);
    }

    /**
     * state true / false sets the flag (globally or for a scope); null inherits.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate(['state' => 'present|nullable|boolean']);
        $scope = $this->scopeFrom($request);
        $state = $request->input('state');

        $this->attempt(fn () => $this->setanjo->feature($key)->for($scope)->toggle($state === null ? null : (bool) $state));

        return $this->respond($key, $scope);
    }

    public function meta(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'meta' => 'present|nullable|array',
            'merge' => 'sometimes|boolean',
        ]);

        $feature = $this->setanjo->feature($key);
        $meta = $request->input('meta');

        $this->attempt(fn () => $request->boolean('merge') ? $feature->setMeta($meta ?? []) : $feature->replaceMeta($meta));

        return $this->respond($key, null);
    }

    public function order(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'present|nullable|integer',
            'keys' => 'present|array',
            'keys.*' => 'string',
        ]);

        $this->attempt(fn () => $this->setanjo->groups()->reorderFeatures($request->input('group_id'), $request->input('keys')));

        return $this->index();
    }

    protected function respond(string $key, ?Model $scope): JsonResponse
    {
        if ($scope !== null) {
            return response()->json(['data' => collect($this->catalog->forScope($scope)['features'])->firstWhere('key', $key)]);
        }

        return response()->json(['data' => $this->catalog->feature($key)]);
    }
}

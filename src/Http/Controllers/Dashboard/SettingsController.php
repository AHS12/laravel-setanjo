<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Exceptions\DashboardException;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->settings()]);
    }

    /**
     * Set a value globally, or for one scope with scope_type + scope_id.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => 'present']);
        $scope = $this->scopeFrom($request);
        $this->ensureEditable($key);

        $this->attempt(fn () => $this->manager($scope)->set($key, $request->input('value')));

        return $this->respond($key, $scope);
    }

    /**
     * Remove the stored value: a scope falls back to the next scope up, global falls back to the default.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $scope = $this->scopeFrom($request, allowMissing: true);
        $this->ensureEditable($key);

        $this->manager($scope)->forget($key);

        return $this->respond($key, $scope);
    }

    /**
     * Replace (default) or merge the meta stored for a key.
     */
    public function meta(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'meta' => 'present|nullable|array',
            'merge' => 'sometimes|boolean',
        ]);

        $meta = $request->input('meta');

        $this->attempt(fn () => $request->boolean('merge')
            ? $this->setanjo->setMeta($key, $meta ?? [])
            : $this->setanjo->replaceMeta($key, $meta));

        return $this->respond($key, null);
    }

    /**
     * Put settings into a group (null = ungrouped) in the given order.
     */
    public function order(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'present|nullable|integer',
            'keys' => 'present|array',
            'keys.*' => 'string',
        ]);

        $this->attempt(fn () => $this->setanjo->groups()->reorderSettings($request->input('group_id'), $request->input('keys')));

        return $this->index();
    }

    protected function manager(?Model $scope): SetanjoManager
    {
        return $scope === null ? $this->setanjo : $this->setanjo->for($scope);
    }

    protected function ensureEditable(string $key): void
    {
        $row = $this->catalog->setting($key);

        if ($row !== null && $row['readonly']) {
            throw DashboardException::forbidden(__('setanjo::dashboard.locked', ['key' => $key]));
        }
    }

    protected function respond(string $key, ?Model $scope): JsonResponse
    {
        if ($scope !== null) {
            $row = collect($this->catalog->forScope($scope)['settings'])->firstWhere('key', $key);

            return response()->json(['data' => $row]);
        }

        return response()->json(['data' => $this->catalog->setting($key)]);
    }
}

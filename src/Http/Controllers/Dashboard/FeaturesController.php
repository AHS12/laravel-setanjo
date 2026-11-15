<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Exceptions\DashboardException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $state = $request->input('state');
        // Clearing (null) also works for a deleted record; setting a state needs a live one.
        $scope = $this->scopeFrom($request, allowMissing: $state === null);

        $this->attempt(fn () => $this->setanjo->feature($key)->for($scope)->toggle($state === null ? null : (bool) $state));

        return $this->respond($key, $scope);
    }

    /**
     * One flag; with scope_type + scope_id, as that record sees it (override, effective state, chain).
     */
    public function show(Request $request, string $key): JsonResponse
    {
        if ($this->catalog->feature($key) === null) {
            throw DashboardException::notFound(__('setanjo::dashboard.unknown_key', ['key' => $key]));
        }

        return $this->respond($key, $this->scopeFrom($request));
    }

    /**
     * Every record that overrides the flag, newest first.
     */
    public function overrides(Request $request, string $key): JsonResponse
    {
        return $this->overridesPage($request, $key, feature: true);
    }

    /**
     * Turn the flag on, off or back to inherit for many records of one scope type at once.
     * With dry_run nothing is written: the response says which ids match and what would change.
     */
    public function bulk(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'scope_type' => 'required|string',
            'ids' => 'required|array|min:1|max:1000',
            'ids.*' => 'nullable', // blank lines of a pasted list are skipped
            'state' => 'present|nullable|boolean',
            'dry_run' => 'sometimes|boolean',
        ]);

        if ($this->catalog->feature($key) === null) {
            throw DashboardException::notFound(__('setanjo::dashboard.unknown_key', ['key' => $key]));
        }

        $alias = (string) $request->input('scope_type');
        $state = $request->input('state') === null ? null : (bool) $request->input('state');
        $ids = collect($request->input('ids'))
            ->filter(fn ($id) => is_scalar($id))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values()
            ->all();

        // Keys compare case-insensitively so pasted UUIDs match however they were written.
        $found = array_change_key_case($this->scopes->findMany($alias, $ids));
        $overrides = $this->setanjo->featureRepository();
        $matched = [];
        $missing = [];
        $notAllowed = [];

        foreach ($ids as $id) {
            $model = $found[strtolower($id)] ?? null;

            if ($model === null) {
                $missing[] = $id;
            } elseif (! $this->catalog->allows($key, $model)) {
                $notAllowed[] = $id;
            } else {
                $matched[] = $model;
            }
        }

        $overrides->loadOverrides($matched);
        $changes = array_values(array_filter($matched, function (Model $model) use ($overrides, $key, $state) {
            $current = $overrides->overrides($model)[$key] ?? null;

            return ($current === null ? null : (bool) $current) !== $state;
        }));

        if (! $request->boolean('dry_run')) {
            DB::transaction(function () use ($changes, $key, $state) {
                foreach ($changes as $model) {
                    $this->setanjo->feature($key)->for($model)->toggle($state);
                }
            });
        }

        return response()->json([
            'data' => [
                'dry_run' => $request->boolean('dry_run'),
                'matched' => count($matched),
                'changed' => count($changes),
                'unchanged' => count($matched) - count($changes),
                'missing' => $missing,
                'not_allowed' => $notAllowed,
                'records' => array_map(fn (Model $model) => $this->scopes->present($model, $alias), array_slice($changes, 0, 20)),
                'feature' => $this->catalog->feature($key),
            ],
        ]);
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

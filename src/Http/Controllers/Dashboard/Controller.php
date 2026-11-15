<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Dashboard\Catalog;
use Ahs12\Setanjo\Dashboard\ScopeRegistry;
use Ahs12\Setanjo\Exceptions\DashboardException;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\SetanjoManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

abstract class Controller
{
    public function __construct(
        protected SetanjoManager $setanjo,
        protected ScopeRegistry $scopes,
        protected Catalog $catalog,
    ) {}

    /**
     * The scope named by `scope_type` (alias) + `scope_id`, or null for global.
     *
     * With $allowMissing, a deleted record that still has overrides comes back as a ghost
     * so those overrides can be cleared. Writing new values to it is never allowed.
     */
    protected function scopeFrom(Request $request, bool $allowMissing = false): ?Model
    {
        $request->validate([
            'scope_type' => 'nullable|string',
            'scope_id' => 'required_with:scope_type',
        ]);

        $type = $request->input('scope_type');

        if ($type === null) {
            return null;
        }

        return $allowMissing
            ? $this->scopeOrGhost($type, (string) $request->input('scope_id'))
            : $this->scopes->find($type, (string) $request->input('scope_id'));
    }

    /**
     * The record, or a ghost of it when it was deleted but overrides are still stored.
     */
    protected function scopeOrGhost(string $type, string $id): Model
    {
        $model = $this->scopes->findOrNull($type, $id);

        if ($model !== null) {
            return $model;
        }

        if ($this->scopes->acceptsKey($type, $id)
            && $this->scopes->state($type, $id) === ScopeRegistry::MISSING
            && $this->catalog->hasOverrides($this->scopes->type($type)['class'], $id)) {
            return $this->scopes->ghost($type, $id);
        }

        return $this->scopes->find($type, $id); // throws the right 404
    }

    /**
     * The records that override one key, paged: `scope_type` limits it to one type.
     */
    protected function overridesPage(Request $request, string $key, bool $feature): JsonResponse
    {
        $request->validate([
            'scope_type' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $exists = $feature ? $this->catalog->feature($key) !== null : $this->catalog->setting($key) !== null;

        if (! $exists) {
            throw DashboardException::notFound(__('setanjo::dashboard.unknown_key', ['key' => $key]));
        }

        $page = $this->catalog->keyOverrides(
            $key,
            $feature,
            $request->input('scope_type'),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 50),
        );

        return response()->json(['data' => $page['data'], 'meta' => Arr::except($page, 'data')]);
    }

    /**
     * Run a write and turn package validation errors into 422 responses.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function attempt(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidSettingValueException $e) {
            throw DashboardException::invalid($e->getMessage(), $e->errors() ?: ['value' => [$e->getMessage()]]);
        } catch (InvalidArgumentException $e) {
            throw DashboardException::invalid($e->getMessage());
        }
    }
}

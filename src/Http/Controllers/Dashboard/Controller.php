<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Dashboard\Catalog;
use Ahs12\Setanjo\Dashboard\ScopeRegistry;
use Ahs12\Setanjo\Exceptions\DashboardException;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\SetanjoManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
     */
    protected function scopeFrom(Request $request): ?Model
    {
        $request->validate([
            'scope_type' => 'nullable|string',
            'scope_id' => 'required_with:scope_type',
        ]);

        $type = $request->input('scope_type');

        return $type === null ? null : $this->scopes->find($type, $request->input('scope_id'));
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

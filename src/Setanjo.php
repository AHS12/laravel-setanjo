<?php

namespace Ahs12\Setanjo;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Dashboard access control.
 *
 * Viewing: Setanjo::auth() callback when set; otherwise allowed in the local
 * environment, or when the app defines a `viewSetanjo` gate that passes.
 *
 * Changing: never in readonly mode; otherwise Setanjo::manage() callback when set,
 * else the `manageSetanjo` gate when the app defines it, else whoever may view.
 */
class Setanjo
{
    /** @var (Closure(Request): bool)|null */
    protected static ?Closure $authUsing = null;

    /** @var (Closure(Request): bool)|null */
    protected static ?Closure $manageUsing = null;

    /**
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function auth(?Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function manage(?Closure $callback): void
    {
        static::$manageUsing = $callback;
    }

    public static function check(Request $request): bool
    {
        if (static::$authUsing !== null) {
            return (bool) (static::$authUsing)($request);
        }

        if (app()->environment('local')) {
            return true;
        }

        return Gate::has('viewSetanjo') && Gate::forUser($request->user())->check('viewSetanjo');
    }

    public static function canManage(Request $request): bool
    {
        if (config('setanjo.dashboard.readonly', false) || ! static::check($request)) {
            return false;
        }

        if (static::$manageUsing !== null) {
            return (bool) (static::$manageUsing)($request);
        }

        if (Gate::has('manageSetanjo')) {
            return Gate::forUser($request->user())->check('manageSetanjo');
        }

        return true;
    }

    /**
     * Drop the callbacks (used between tests).
     */
    public static function flush(): void
    {
        static::$authUsing = null;
        static::$manageUsing = null;
    }
}

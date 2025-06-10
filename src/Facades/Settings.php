<?php

namespace Ahs12\Setanjo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ahs12\Setanjo\SetanjoManager for($tenant)
 * @method static \Ahs12\Setanjo\SetanjoManager forTenantId(int $tenantId, ?string $modelClass = null)
 * @method static mixed get(string $key, $default = null)
 * @method static \Ahs12\Setanjo\SetanjoManager set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static \Ahs12\Setanjo\SetanjoManager forget(string $key)
 * @method static \Illuminate\Support\Collection all()
 * @method static \Ahs12\Setanjo\SetanjoManager flush()
 *
 * @see \Ahs12\Setanjo\SetanjoManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'setanjo';
    }
}

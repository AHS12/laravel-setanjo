<?php

namespace Ahs12\Setanjo\Facades;

use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ahs12\Setanjo\SetanjoManager for($tenant)
 * @method static \Ahs12\Setanjo\SetanjoManager forTenantId(int $tenantId, ?string $modelClass = null)
 * @method static \Illuminate\Database\Eloquent\Model|null tenant()
 * @method static mixed get(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, $default = null)
 * @method static \Ahs12\Setanjo\SetanjoManager set(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, mixed $value, ?array $meta = null)
 * @method static bool has(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key)
 * @method static \Ahs12\Setanjo\SetanjoManager forget(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key)
 * @method static \Illuminate\Support\Collection all()
 * @method static \Ahs12\Setanjo\SetanjoManager flush()
 * @method static mixed effective(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, $default = null)
 * @method static \Ahs12\Setanjo\ValueObjects\ResolvedValue explain(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key)
 * @method static \Ahs12\Setanjo\ValueObjects\ResolvedValue resolve(string $key, ?\Illuminate\Database\Eloquent\Model $subject)
 * @method static mixed meta(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, ?string $item = null, mixed $default = null)
 * @method static \Ahs12\Setanjo\SetanjoManager setMeta(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, array $meta)
 * @method static \Ahs12\Setanjo\SetanjoManager replaceMeta(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key, ?array $meta)
 * @method static array describe(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key)
 * @method static \Ahs12\Setanjo\Definitions\DefinitionRegistry definitions()
 * @method static \Ahs12\Setanjo\Contracts\SettingDefinition define(string $key, array $attributes = [])
 * @method static \Ahs12\Setanjo\Contracts\SettingDefinition defineFeature(string $key, array $attributes = [])
 * @method static \Ahs12\Setanjo\Features\PendingFeature feature(string|\BackedEnum|\Ahs12\Setanjo\Contracts\SettingDefinition $key)
 * @method static \Ahs12\Setanjo\Features\PendingFeatures features()
 * @method static \Ahs12\Setanjo\Groups\GroupManager groups()
 * @method static \Ahs12\Setanjo\SetanjoManager resolveScopesUsing(?\Closure $resolver)
 * @method static \Ahs12\Setanjo\SetanjoManager resolveDefaultSubjectUsing(?\Closure $resolver)
 * @method static \Ahs12\Setanjo\SetanjoManager forgetCache(?\Illuminate\Database\Eloquent\Model $scope = null)
 * @method static \Ahs12\Setanjo\SetanjoManager flushCache()
 *
 * @see SetanjoManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'setanjo';
    }
}

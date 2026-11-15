# Upgrade Guide

## From 2.x to 3.0

v3 is additive: the 2.x API (`Settings::get/set/has/forget/all/flush/for/forTenantId`,
`HasSettings`) keeps working. Existing rows are read unchanged.

### 1. Update the package

```bash
composer require "ahs12/laravel-setanjo:^3.0"
```

### 2. Publish and run the new migrations

```bash
php artisan vendor:publish --tag="setanjo-migrations"
php artisan migrate
```

This adds four migrations:

| Migration | Change |
| --- | --- |
| `create_setanjo_groups_table` | Creates `setanjo_groups` (or adds missing columns to an existing `groups_table`) |
| `add_v3_columns_to_setanjo_settings_table` | Adds `group_id`, `sort_order`, `meta`, `is_locked`; widens `type` to 20 chars |
| `create_setanjo_history_table` | Creates `setanjo_history` (only written to when `history.enabled` is true) |
| `create_setanjo_features_tables` | Creates `setanjo_features` and `setanjo_feature_overrides` |

Every migration checks for existing tables and columns first, so it is safe to run on
databases that already have some of them.

### 3. Merge the new config keys

If you published `config/setanjo.php`, add the new keys from the package config:
`groups_table`, `definitions`, `validation.values`, `meta`, `features`, `history`.

### 4. (Optional) Move your setting keys into definitions

Implement `Ahs12\Setanjo\Contracts\SettingDefinition` on your settings enum (use the
`Ahs12\Setanjo\Traits\DefinesSetting` trait), list it under `definitions`, then run:

```bash
php artisan setanjo:sync --dry-run
php artisan setanjo:sync
```

`setanjo:sync` reports keys stored with a type that differs from the definition. Convert them
with `--force-types` once you have checked the list.

## Behaviour changes

| Area | 2.x | 3.0 |
| --- | --- | --- |
| Feature flags | boolean rows in `settings` | own tables; `Settings::set()` throws for keys defined as features |
| `has()` on a stored `null` | `false` | `true` |
| Changing a key's type (e.g. int → array) | threw "Array to string conversion" | works |
| `get()` with no stored value and no `$default` | `null` | the definition default (defined keys only) |
| `set()` on a defined key | type guessed from the value | type from the definition; value validated and coerced |
| Repository binding | new instance per resolve | singleton shared with the manager |
| In-memory values in queue workers / Octane | kept for the whole process | dropped before each job / request |
| Cache keys | `{prefix}:settings:{scope}` (tags on redis/memcached) | `{prefix}:v{n}:settings:{scope}`, no tags; old keys simply expire |
| `setanjo:clear-cache --tenant` with a bad value | `exit(1)` | returns exit code 1 |

### Custom repositories

`SettingsRepositoryInterface` has new methods (`record`, `records`, `loadMany`, `find`,
`updateAttributes`, `forgetCache`, `flushCache`, `reset`) and `set()` takes an optional
`?SettingType $type`. Custom implementations must add them; extending
`DatabaseSettingsRepository` is the easiest path.

## Apps that built groups and flags themselves (e.g. MEM)

If you already have a `settings_group` table and a `settings.group_id` column:

1. Set `'groups_table' => 'settings_group'` in `config/setanjo.php` **before** migrating.
   The groups migration then only adds `icon` and `meta`; the settings migration skips
   `group_id` because it exists.
2. Make your `SettingKey` enum implement `SettingDefinition`:
   - `isFeatureFlag()` → `isFeature()`
   - `defaultValue()` → `default()`
   - `type()` must return `SettingType` instead of a string
   - `group()` may keep returning a name like `'Finance'`; it is slugged (`finance`)
   - move "Number of days…" style descriptions into `meta()`: `['unit' => 'days']`
3. Move the flag rows out of `settings` (global rows become the flag's global state, user rows become overrides):

   ```bash
   php artisan setanjo:migrate-feature-flags --dry-run
   php artisan setanjo:migrate-feature-flags
   ```

   `setanjo:sync` warns while flag rows are still in `settings`, and `--prune` leaves them alone.
4. Replace app code with package APIs:

| App code | Package |
| --- | --- |
| `SettingsService::isFeatureFlagEnabled($user, $flag)` | `Settings::feature($flag)->for($user)->enabled()` |
| `setGlobalFeatureFlag` / `setUserFeatureFlag(..., 'inherit')` | `feature()->enable()/disable()/inherit()` |
| `feature:{key}` middleware | `setanjo.feature:{key}` |
| `settings:sync` command | `setanjo:sync --prune` |
| Re-implemented cache-key clearing | `Settings::forgetCache($scope)` / `Settings::flushCache()` |
| `audit()->record(...)` after each write | a listener on `SettingUpdated` / `FeatureToggled` |
| Spatie permission cache reset after flag changes | a listener on `FeatureToggled` |

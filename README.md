# laravel-setanjo - Laravel Settings Package with Multi-Tenant Support

<p align="center">
<a href="https://github.com/ahs12/laravel-setanjo/actions"><img src="https://github.com/ahs12/laravel-setanjo/actions/workflows/run-tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/ahs12/laravel-setanjo"><img src="https://img.shields.io/packagist/dt/ahs12/laravel-setanjo" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/ahs12/laravel-setanjo"><img src="https://img.shields.io/packagist/v/ahs12/laravel-setanjo" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/ahs12/laravel-setanjo"><img src="https://img.shields.io/packagist/l/ahs12/laravel-setanjo" alt="License"></a>
</p>

A powerful Laravel package for managing application settings and configurations. Store global application settings or model-specific configurations (user preferences, company settings, etc.) with automatic type casting, caching, and a clean API. Perfect for feature flags, A/B testing, user preferences, and dynamic configuration management.

**Note**: This package does **not** provide multi-tenancy features for your application. However, if your Laravel project already has multi-tenancy implemented, this package can store tenant-specific settings alongside your existing tenant architecture.

## Features

-   🏢 **Multi-Tenant Ready**: Works with existing multi-tenant applications
-   🗃️ **Model-Specific Settings**: Store settings for any Eloquent model (User, Company, etc.)
-   🏛️ **Global Settings**: Application-wide settings without model scope
-   ⚡ **Caching**: Optional caching with configurable cache store
-   🔒 **Validation**: Validate models and prevent unauthorized access
-   📦 **Clean API**: Simple, intuitive API for setting and retrieving values
-   🧪 **Fully Tested**: Comprehensive test suite included
-   ✅ **Type Safety**: Automatic type detection and conversion
-   📝 **Definitions in Code**: Declare settings on an enum (default, type, label, group, rules, meta)
-   🚩 **Feature Flags**: Global flags with per-user / per-tenant / any-scope overrides
-   🔗 **Scope Chain**: Resolve effective values through user → branch → tenant → global → default
-   🏷️ **Metadata**: Free-form JSON meta per setting (unit, min, max, options, help, sensitive, ...)
-   🗂️ **Groups & Ordering**: Group settings and order groups and settings (drag-and-drop ready)
-   🔄 **Sync, Export, Import**: Keep the database in line with code; move settings between environments
-   📣 **Events & History**: Hook audit logging into events, or let the package record history
-   🖥️ **Dashboard**: A built-in UI for settings, groups, flags, overrides, drift, history and import/export

## Requirements
- PHP 8.4 or higher
- Laravel 12.0 or 13.0

### Version Compatibility

| Package version | Laravel | PHP | Testbench | Status |
| --- | --- | --- | --- | --- |
| `3.x` | 12, 13 | `^8.4` | 10, 11 | Active |
| `2.x` | 12, 13 | `^8.4` | 10, 11 | Maintenance (bug fixes) |
| `1.x` | 10, 11, 12 | `^8.2` | 8, 9, 10 | Legacy |

If you are on Laravel 10/11 or PHP 8.2/8.3, install the `1.x` line:

```bash
composer require "ahs12/laravel-setanjo:^1.0"
```

To stay on `2.x` for now:

```bash
composer require "ahs12/laravel-setanjo:^2.0"
```

## Installation

1. **Install the package:**

```bash
composer require ahs12/laravel-setanjo
```

2. **Publish config and migrations, then migrate:**

```bash
php artisan setanjo:install
```

Or step by step:

```bash
php artisan vendor:publish --tag="setanjo-migrations"
php artisan migrate
```

Upgrading from 2.x? See [UPGRADE.md](UPGRADE.md).

3. **Configure tenancy mode (optional):**

By default, the package runs in **strict mode** (single tenant model type).

For basic setup with User model, no configuration needed. For other models or multiple tenant types:

```bash
php artisan vendor:publish --tag="setanjo-config"
```

## Quick Setup

### Option 1: Strict Mode (Default - Single Tenant Type)

Perfect for simple user preferences or single-model tenancy:

```php
// Uses App\Models\User by default
// No configuration needed
```

### Option 2: Custom Strict Mode

For using a different model as the single tenant type:

```php
// config/setanjo.php
'tenancy_mode' => 'strict',
'strict_tenant_model' => App\Models\Company::class,
```

### Option 3: Polymorphic Mode

For multiple tenant model types (SaaS apps, complex multi-tenancy):

```php
// config/setanjo.php
'tenancy_mode' => 'polymorphic',
'allowed_tenant_models' => [
    App\Models\User::class,
    App\Models\Company::class,
    App\Models\Organization::class,
],
```

## Basic Usage

### Global Settings

```php
use Ahs12\Setanjo\Facades\Settings;

// Set application-wide settings
Settings::set('app_name', 'My Application');
Settings::set('maintenance_mode', false);

// Get with default fallback
$appName = Settings::get('app_name', 'Default Name');
```

### Tenant Settings (Strict Mode)

```php
// User-specific settings (default tenant model)
$user = User::find(1);
Settings::for($user)->set('theme', 'dark');
Settings::for($user)->set('language', 'en');

// Or by tenant ID
Settings::forTenantId(1)->set('notifications', true);

echo Settings::for($user)->get('theme'); // 'dark'
```

### Tenant Settings (Polymorphic Mode)

```php
// Different model types as tenants
$user = User::find(1);
$company = Company::find(1);

Settings::for($user)->set('theme', 'dark');
Settings::for($company)->set('timezone', 'UTC');

// Or by tenant ID with model class
Settings::forTenantId(1, Company::class)->set('currency', 'USD');
```

### Type Casting

```php
Settings::set('is_active', true);        // Boolean
Settings::set('max_users', 100);         // Integer
Settings::set('rate', 4.99);             // Float
Settings::set('features', ['api', 'sms']); // Array
Settings::set('config', '{"theme": "dark", "lang": "en"}'); // JSON string
Settings::set('metadata',['version' => '1.0', 'author' => 'John']); // Object

// Values are returned with correct types
$isActive = Settings::get('is_active'); // Returns boolean true
$maxUsers = Settings::get('max_users'); // Returns integer 100
$rate = Settings::get('rate');          // Returns float 4.99
$features = Settings::get('features');  // Returns array ['api', 'sms']
$config = Settings::get('config');      // Returns the JSON string as stored
$metadata = Settings::get('metadata');  // Returns array ['version' => '1.0', 'author' => 'John']
```

## Definitions

Declare settings and feature flags in code. Values stay in the database; the definition
supplies the default, type, label, group, validation rules and meta.

```php
use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Traits\DefinesSetting;

enum SettingKey: string implements SettingDefinition
{
    use DefinesSetting; // sensible defaults, override what you need

    case COMPANY_NAME = 'company_name';
    case AUDIT_RETENTION = 'audit_retention';
    case TAX_FEATURES = 'tax_features';

    public function default(): mixed
    {
        return match ($this) {
            self::COMPANY_NAME => 'Acme',
            self::AUDIT_RETENTION => 180,
            self::TAX_FEATURES => false,
        };
    }

    public function group(): ?string
    {
        return match ($this) {
            self::COMPANY_NAME => 'Company',
            default => 'System',
        };
    }

    public function rules(): array|string
    {
        return $this === self::COMPANY_NAME ? 'string|max:100' : [];
    }

    public function meta(): array
    {
        return match ($this) {
            self::AUDIT_RETENTION => ['unit' => 'days', 'min' => 1, 'max' => 3650],
            default => [],
        };
    }

    public function isFeature(): bool
    {
        return $this === self::TAX_FEATURES;
    }
}
```

Register it in `config/setanjo.php` (inline arrays work too):

```php
'definitions' => [
    App\Enums\SettingKey::class,
    'site_name' => ['default' => 'My App', 'group' => 'general'],
    'new_checkout' => ['feature' => true],
],
```

With a definition:

- `Settings::get('audit_retention')` returns the default (`180`) until a value is stored.
- The type is fixed: `Settings::set('audit_retention', '90')` stores the integer `90`.
- Values are validated against `rules()` and meta `min` / `max` / `options`, otherwise
  `InvalidSettingValueException` is thrown.
- Keys accept enum cases: `Settings::get(SettingKey::COMPANY_NAME)`.
- `scopes()` limits which models may override a key, e.g. `[Company::class]`.

Then create the rows and groups:

```bash
php artisan setanjo:sync            # add missing keys, create groups
php artisan setanjo:sync --dry-run  # preview
php artisan setanjo:sync --prune    # also delete keys that are no longer defined
```

## Effective Values and the Scope Chain

`get()` reads only the current scope. `effective()` walks a chain of scopes, most specific
first, then global, then the definition default.

```php
// AppServiceProvider::boot()
Settings::resolveScopesUsing(fn ($user) => [$user, $user?->branch, $user?->tenant]);

Settings::for($user)->effective('audit_retention'); // user → branch → tenant → global → default
Settings::effective('audit_retention');             // uses the authenticated user

$resolved = Settings::for($user)->explain('audit_retention');
$resolved->value;  // 90
$resolved->source; // ValueSource::SCOPE | GLOBAL | DEFAULT
$resolved->scope;  // the model that supplied the value
```

The whole chain loads in one query and is cached per scope.

## Feature Flags

Flags are stored in their own tables, apart from settings: `setanjo_features` holds one row per
flag (global state, name, group, order, meta) and `setanjo_feature_overrides` holds one row per
scope override. `Settings::set()` refuses keys defined as features.

```php
Settings::feature('tax_features')->enabled();                 // for the authenticated user
Settings::feature('tax_features')->for($user)->enabled();

Settings::feature('tax_features')->enable();                  // global
Settings::feature('tax_features')->for($tenant)->disable();   // tenant override
Settings::feature('tax_features')->for($user)->inherit();     // remove the user override
Settings::feature('tax_features')->for($user)->override();    // true, false or null (inherits)

Settings::features()->for($user)->all();                       // ['tax_features' => true, ...]
```

On a model, add `HasFeatures`: `$user->hasFeature('tax_features')`, `$user->enableFeature(...)`,
`$user->inheritFeature(...)`, `$user->featureStates()`.

Routes, Blade and Gate:

```php
Route::middleware('setanjo.feature:tax_features')->group(...);          // 403 when disabled
Route::middleware('setanjo.feature:tax_features,new_checkout')->get(...); // all must be on
```

```blade
@feature('tax_features')
    ...
@else
    ...
@endfeature

{{-- effective value, escaped --}}
@setting('company_name')
```

Flag meta and admin helpers:

```php
Settings::feature('tax_features')->meta('owner');
Settings::feature('tax_features')->setMeta(['owner' => 'finance']);
Settings::feature('tax_features')->for($user)->describe(); // effective state, source, override count, meta
Settings::featureRepository()->overrideCounts();            // ['tax_features' => 12, ...]
Settings::featureRepository()->overridesOf('tax_features'); // overrides with their scope models
Settings::groups()->assignFeature('tax_features', 'finance');
```

Set `features.gate` to `true` to use `Gate::allows('feature:tax_features')`. Set
`features.strict` to `true` to throw on feature keys that have no feature definition.

## Metadata

Every setting can carry free-form JSON meta. The UI and validation use these keys when
present; anything else is kept as-is.

| Key | Purpose |
| --- | --- |
| `unit` | Display suffix: `days`, `%`, `BDT`, `MB` |
| `min` / `max` / `step` | Number bounds (validated) |
| `input` | Editor: `toggle`, `number`, `text`, `textarea`, `select`, `json`, `color`, `date` |
| `options` | Allowed values for selects (validated) |
| `help` / `placeholder` | Help text |
| `sensitive` | Masked in history (and the dashboard) |
| `readonly` | Shown but not editable in the dashboard |
| `tags` | Labels for search and filtering |

```php
Settings::meta('audit_retention');                    // ['unit' => 'days', 'min' => 1, ...]
Settings::meta('audit_retention', 'unit');            // 'days'
Settings::setMeta('audit_retention', ['help' => 'Days to keep']); // merge
Settings::replaceMeta('audit_retention', ['unit' => 'weeks']);    // overwrite
Settings::set('audit_retention', 90, meta: ['unit' => 'days']);
Settings::describe('audit_retention');                // value, source, type, group, meta, ...
```

Effective meta is the definition's `meta()` merged with the meta stored in the database
(stored wins). Meta belongs to the key, not to a scope.

## Groups

Group membership and order live on the global row of each setting.

```php
$group = Settings::groups()->create(['name' => 'Mail', 'icon' => 'envelope']);
Settings::groups()->update('mail', ['description' => 'Outgoing mail']);
Settings::groups()->reorder(['mail', 'company', 'system']);          // group order
Settings::groups()->assign('smtp_host', 'mail');                      // move a setting
Settings::groups()->reorderSettings('mail', ['smtp_host', 'smtp_port']); // order inside a group
Settings::groups()->keys('mail');                                     // ['smtp_host', 'smtp_port']
Settings::groups()->delete('mail');                                   // settings become ungrouped
```

Already have a groups table? Set `groups_table` (e.g. `'settings_group'`); the migration only
adds missing columns.

## Events and History

Events: `SettingUpdated`, `SettingForgotten`, `SettingMetaUpdated`, `SettingsFlushed`,
`FeatureToggled`, `SettingGroupChanged`, `SettingsSynced`. Listen to them for audit logs or
to clear other caches.

Set `history.enabled` to `true` to record every change (value, meta, scope, causer) in
`setanjo_history`. Values of settings with meta `sensitive => true` are masked.

## Dashboard

Setanjo ships a web UI at `/setanjo`. Host apps need no Livewire, Vue or Node: the page
inlines its prebuilt assets, so nothing has to be published and `composer update` never
leaves stale assets behind.

| Screen | What you can do |
| --- | --- |
| Settings | Edit values with editors chosen by type and meta (toggle, number + unit, select, JSON, color, ...); drag to reorder settings, move them between groups and reorder groups; reset to default |
| Feature flags | Set each flag's global state (default / on / off); see override counts |
| Overrides | Pick a record of any configured scope type, then set or remove its flag and setting overrides; every row shows the effective value and where it came from |
| Groups | Create, edit, delete and reorder groups |
| Meta | Edit the meta stored for a setting or flag next to the meta declared in code (key/value or raw JSON) |
| Drift | Compare definitions with the database; preview `setanjo:sync`, then run it |
| History | Browse recorded changes by key, action and scope (when `history.enabled`) |
| Import / export | Download a `setanjo:export` file; check and import one |

Locked settings (`is_locked`) and settings with meta `readonly => true` cannot be changed from
the dashboard. Values with meta `sensitive => true` are never sent to the browser.

### Access

Every dashboard route (the page and its JSON API) runs through `setanjo.dashboard.middleware`,
the same way Telescope and Pulse work:

```php
use Ahs12\Setanjo\Http\Middleware\Authorize;

'middleware' => ['web', Authorize::class],
```

**`Authorize`** (the default) lets the dashboard open in the `local` environment only. Elsewhere,
define a gate:

```php
// AppServiceProvider::boot()
Gate::define('viewSetanjo', fn (User $user) => $user->isAdmin());
```

or take full control with a callback, which wins over the gate and the environment:

```php
use Ahs12\Setanjo\Setanjo;

Setanjo::auth(fn (Request $request) => $request->user()?->isAdmin() ?? false);
```

**Your own middleware.** Add middleware to the list, or replace `Authorize` with it:

```php
'middleware' => ['web', 'auth', Authorize::class],        // your login + the viewSetanjo gate
'middleware' => ['web', 'auth', 'role:Super Admin'],      // your middleware alone decides
'middleware' => ['web', 'auth.basic', 'can:manage-settings'],
```

**Never public by accident.** Without `Authorize` in the list, outside the `local` environment
someone must be signed in, so `['web']` alone answers 403. Set `require_auth` to `false` only when
something else protects the path (a VPN, an IP allow-list or auth at the proxy).

**Changes** are always checked, whatever the list: nothing in `readonly` mode, otherwise
`Setanjo::manage()` when set, else the `manageSetanjo` gate when defined, else whoever may view.

```php
Gate::define('manageSetanjo', fn (User $user) => $user->isSuperAdmin());
// or
Setanjo::manage(fn (Request $request) => $request->user()?->isSuperAdmin() ?? false);
```

**Token-only APIs (Sanctum bearer tokens).** A browser opening `/setanjo` does not send the API
token, so `auth:sanctum` cannot protect the page. Use a browser-friendly login instead, for
example `['web', 'auth.basic:web', 'role:Super Admin,web']` (HTTP Basic against the `web` guard),
a session login page, or a middleware of your own such as an IP allow-list.

### Configuration

```php
'dashboard' => [
    'enabled' => env('SETANJO_DASHBOARD_ENABLED', true),   // false: no routes at all
    'path' => env('SETANJO_DASHBOARD_PATH', 'setanjo'),
    'domain' => env('SETANJO_DASHBOARD_DOMAIN'),
    'middleware' => ['web', Authorize::class],               // see Access
    'require_auth' => env('SETANJO_DASHBOARD_REQUIRE_AUTH', true),
    'readonly' => env('SETANJO_DASHBOARD_READONLY', false),  // view only, for everyone
],

// Models the Overrides screen can manage. Empty = the tenant models from tenancy_mode.
'scopes' => [
    App\Models\User::class => [
        'label' => 'name',                              // column or fn ($user) => ...
        'search' => ['name', 'email'],                  // columns searched with LIKE
        'description' => fn ($user) => $user->email,
        'without_scopes' => true,                       // ignore global scopes (tenant, branch)
    ],
    App\Models\Branch::class => ['search' => ['title', 'code']],
],
```

Any model can be a scope, not just users. On the Overrides screen you pick the type (User,
Company, Branch…), find a record and set overrides for it; inherited values show where they
come from. Things to know:

- **Label and search columns.** Without `label`/`search`, the first of `name`, `title`, `label`,
  `email`, `slug`, `code` the table has is used, and records without one show as `Branch #12`.
  Pasting an id always works: digits for integer keys, a valid UUID/ULID for `HasUuids`/`HasUlids`.
- **Global scopes.** The dashboard queries models with their global scopes applied. If a tenant or
  branch scope (one that reads the logged-in user) would hide records from the admin using the
  dashboard, set `'without_scopes' => true` for that type. Opening a hidden record says so.
- **Soft deletes.** Soft-deleted records still open (marked *trashed*); their overrides apply
  again if they are restored. They are left out of search.
- **Deleted records.** Overrides of a record that was deleted are listed as *deleted*. Opening it
  shows only what is stored, which can be cleared one by one or with **Clear all overrides**. The
  Drift screen lists them, and overrides stored for models the dashboard does not know.
- **UUID/ULID scope models** need `Schema::morphUsingUuids()` (or `morphUsingUlids()`) before the
  setanjo migrations run, like any polymorphic relation, so the scope id columns can hold them.

The page uses the app's CSP nonce when one is set with `Vite::useCspNonce()`. Alpine.js needs
`'unsafe-eval'` in `script-src`.

The UI talks to a JSON API under `/setanjo/api` (same middleware and access rules). Every write
goes through the package API, so validation, events, history and cache invalidation behave
exactly as in code.

## Commands

| Command | Purpose |
| --- | --- |
| `setanjo:install` | Publish config and migrations, optionally migrate |
| `setanjo:sync` | Sync definitions into the database (`--dry-run`, `--prune`, `--reset-meta`, `--reset-groups`, `--force-types`) |
| `setanjo:export {path?}` | Export settings, meta and groups as JSON (`--global`, `--scope=App\Models\User:1`) |
| `setanjo:import {path}` | Import an export (`--dry-run`) |
| `setanjo:clear-cache` | Clear cache (`--all`, `--tenant=App\Models\User:1`) |
| `setanjo:migrate-feature-flags` | Move flags stored as boolean settings rows into the feature tables (`--key=`, `--keep`, `--dry-run`) |
| `setanjo:install-defaults` | Deprecated: use definitions and `setanjo:sync` |

## Caching

Enable with `SETANJO_CACHE_ENABLED=true`. Writes invalidate only the affected scope;
`Settings::flushCache()` (or `setanjo:clear-cache --all`) invalidates every scope at once by
bumping a version number, so it works on every cache driver. In-memory values are dropped
before each queue job and Octane request, so long-running workers see fresh values.

## Common Use Cases

### User Preferences

```php
$user = auth()->user();
Settings::for($user)->set('notification_email', true);
Settings::for($user)->set('dashboard_layout', 'grid');
Settings::for($user)->set('timezone', 'America/New_York');
```

### Application Configuration

```php
Settings::set('maintenance_mode', false);
Settings::set('registration_enabled', true);
Settings::set('api_rate_limit', 1000);
```

### Multi-Tenant SaaS (Polymorphic Mode)

```php
// Company-level settings
Settings::for($company)->set('feature_api_enabled', true);
Settings::for($company)->set('user_limit', 50);

// User preferences within company
Settings::for($user)->set('email_notifications', false);
```

## Documentation

For detailed documentation, advanced usage, and configuration options, visit our [documentation site](https://github.com/ahs12/laravel-setanjo/wiki).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

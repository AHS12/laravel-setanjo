<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Repository Driver
    |--------------------------------------------------------------------------
    |
    | The storage driver for settings.Currently supported: "database"
    |
    */
    'repository' => env('SETANJO_REPOSITORY', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for database storage
    |
    */
    'table' => env('SETANJO_TABLE', 'settings'),

    /*
    |--------------------------------------------------------------------------
    | Groups Table
    |--------------------------------------------------------------------------
    |
    | Table holding settings groups. Point it at an existing table (e.g.
    | 'settings_group') to keep groups you already manage; the migration then
    | only adds the columns that are missing.
    |
    */
    'groups_table' => env('SETANJO_GROUPS_TABLE', 'setanjo_groups'),

    /*
    |--------------------------------------------------------------------------
    | Definitions
    |--------------------------------------------------------------------------
    |
    | Settings and feature flags declared in code. Values stay in the database;
    | definitions supply default, type, label, group, validation rules and meta.
    |
    | Accepts enum classes implementing Ahs12\Setanjo\Contracts\SettingDefinition
    | (use the Ahs12\Setanjo\Traits\DefinesSetting trait), or inline arrays:
    |
    |   App\Enums\SettingKey::class,
    |   'site_name' => ['default' => 'My App', 'group' => 'general'],
    |   'retention_days' => ['default' => 30, 'meta' => ['unit' => 'days', 'min' => 1]],
    |   'new_checkout' => ['feature' => true, 'default' => false],
    |
    */
    'definitions' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenantable Column Configuration
    |--------------------------------------------------------------------------
    |
    | The base name for polymorphic relationship columns
    | This will create: {tenantable_column}_type and {tenantable_column}_id
    |
    */
    'tenantable_column' => 'tenantable',

    /*
    |--------------------------------------------------------------------------
    | Tenancy Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes: 'polymorphic', 'strict'
    | - polymorphic: Multiple tenant model types allowed
    | - strict: Single tenant model type only
    |
    */
    'tenancy_mode' => env('SETANJO_TENANCY_MODE', 'strict'),

    /*
    |--------------------------------------------------------------------------
    | Strict Tenant Model
    |--------------------------------------------------------------------------
    |
    | Used only when tenancy_mode is 'strict'
    | Define the single model class that can be used as tenant
    |
    */
    'strict_tenant_model' => env('SETANJO_STRICT_TENANT_MODEL', 'App\\Models\\User'), // App\Models\User::class - common default Laravel user model.

    /*
    |--------------------------------------------------------------------------
    | Allowed Tenant Models
    |--------------------------------------------------------------------------
    |
    | Used only when tenancy_mode is 'polymorphic'
    | Array of model classes that can be used as tenantable
    |
    */
    'allowed_tenant_models' => [
        // App\Models\Company::class,
        // App\Models\Branch::class,
        // App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings caching configuration
    |
    */
    'cache' => [
        'enabled' => env('SETANJO_CACHE_ENABLED', false),
        'store' => env('SETANJO_CACHE_STORE', null), // null = use Laravel's default
        'prefix' => env('SETANJO_CACHE_PREFIX', 'setanjo'),
        'ttl' => env('SETANJO_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | Enable validation for tenant models and settings keys
    |
    */
    'validation' => [
        'enabled' => env('SETANJO_VALIDATION_ENABLED', true),
        'throw_exceptions' => env('SETANJO_THROW_EXCEPTIONS', true),
        // Validate values of defined keys against their rules() and meta (min, max, options)
        'values' => env('SETANJO_VALIDATE_VALUES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    |
    | Every setting can carry free-form JSON meta (unit, min, max, input,
    | options, help, sensitive, tags, or anything your app needs).
    |
    */
    'meta' => [
        'max_bytes' => env('SETANJO_META_MAX_BYTES', 16384),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Flags live in their own tables: one row per flag (global state, name,
    | group, meta) plus one row per scope override.
    |
    | strict: throw when a feature key has no feature definition
    | middleware_alias: route middleware name (null to skip registering it)
    | gate: register Gate::allows('feature:{key}')
    |
    */
    'features' => [
        'table' => env('SETANJO_FEATURES_TABLE', 'setanjo_features'),
        'overrides_table' => env('SETANJO_FEATURE_OVERRIDES_TABLE', 'setanjo_feature_overrides'),
        'strict' => env('SETANJO_FEATURES_STRICT', false),
        'middleware_alias' => 'setanjo.feature',
        'gate' => env('SETANJO_FEATURES_GATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    |
    | Record every value / meta change in a history table. Values of settings
    | with meta 'sensitive' => true are masked.
    |
    */
    'history' => [
        'enabled' => env('SETANJO_HISTORY_ENABLED', false),
        'table' => env('SETANJO_HISTORY_TABLE', 'setanjo_history'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings that will be created when running artisan commands
    | php artisan setanjo:install-defaults
    |
    */
    'defaults' => [
        // 'app_name' => [
        //     'value' => 'Laravel App',
        //     'description' => 'Application name',
        //     'type' => 'string',
        // ],
    ],
];

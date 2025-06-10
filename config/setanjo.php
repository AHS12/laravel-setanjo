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
    'strict_tenant_model' => env('SETANJO_STRICT_TENANT_MODEL', true),

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
    | Model Macro
    |--------------------------------------------------------------------------
    |
    | Enable or disable the automatic registration of Eloquent model macros.
    | When enabled, adds 'settings()' method to all Eloquent models for
    | easy access to tenant-specific settings.
    |
    | Example: $user->settings()->get('theme', 'light')
    |
    */

    'enable_model_macro' => env('SETANJO_ENABLE_MODEL_MACRO', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings caching configuration
    |
    */
    'cache' => [
        'enabled' => env('SETANJO_CACHE_ENABLED', true),
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default settings that will be created when running artisan commands
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

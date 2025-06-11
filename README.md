# laravel-setanjo - Multi-Tenant Laravel Settings Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/run-tests?label=tests)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)

A powerful Laravel package for managing application settings with multi-tenant support. Store global settings or tenant-specific configurations with automatic type casting, caching, and a clean API.

## Features

-   🏢 **Multi-Tenant Support**: Both strict and polymorphic tenancy modes
-   🗃️ **Polymorphic Storage**: Store settings for any model type
-   🏛️ **Global Settings**: Settings without any tenant scope
-   ⚡ **Caching**: Optional caching with configurable cache store
-   🔒 **Validation**: Validate tenant models and prevent unauthorized access
-   📦 **Clean API**: Simple, intuitive API inspired by popular packages
-   🧪 **Fully Tested**: Comprehensive test suite included
-   ✅ **Type Safety**: Automatic type detection and conversion

## Installation

```bash
composer require ahs12/laravel-setanjo
php artisan vendor:publish --tag="laravel-setanjo-migrations"
php artisan migrate
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-setanjo-config"
```

## Quick Start

### Global Settings

```php
use Ahs12\Setanjo\Facades\Settings;

// Set application-wide settings
Settings::set('app_name', 'My Application');
Settings::set('maintenance_mode', false);

// Get with default fallback
$appName = Settings::get('app_name', 'Default Name');
```

### Tenant-Specific Settings

```php
// Company-specific settings
$company = Company::find(1);
Settings::for($company)->set('company_name', 'Acme Corp');
Settings::for($company)->set('timezone', 'America/New_York');

// For tenant by ID (useful in polymorphic mode)
Settings::forTenantId(1, Company::class)->set('setting', 'value');
// For tenant by ID in strict mode (model class is optional)
Settings::forTenantId(1)->set('setting', 'value');

// User preferences
$user = User::find(1);
Settings::for($user)->set('theme', 'dark');
Settings::for($user)->set('language', 'en');

// Each tenant has isolated settings
echo Settings::for($company)->get('theme'); // null
echo Settings::for($user)->get('theme'); // 'dark'
```

### Automatic Type Casting

```php
Settings::set('is_active', true);        // Boolean
Settings::set('max_users', 100);         // Integer
Settings::set('rate', 4.99);             // Float
Settings::set('features', ['api', 'sms']); // Array

// Values are returned with correct types
$isActive = Settings::get('is_active'); // Returns boolean true
$features = Settings::get('features');  // Returns array
```

## Use Cases

**SaaS Applications**

```php
// Per-tenant feature flags and limits
Settings::for($tenant)->set('feature_api_enabled', true);
Settings::for($tenant)->set('user_limit', 50);
```

**E-commerce Platforms**

```php
// Store-specific configurations
Settings::for($store)->set('currency', 'USD');
Settings::for($store)->set('tax_rate', 8.5);
```

**User Preferences**

```php
// Individual user settings
Settings::for($user)->set('notification_email', true);
Settings::for($user)->set('dashboard_layout', 'grid');
```

**Application Configuration**

```php
// Global app settings
Settings::set('maintenance_mode', false);
Settings::set('registration_enabled', true);
```

## Configuration

The package works out of the box, but you can customize it by publishing the configuration file:

```bash
php artisan vendor:publish --tag="laravel-setanjo-config"
```

Then modify `config/setanjo.php` to configure tenant models and caching:

```php
// config/setanjo.php
return [
    'tenancy_mode' => 'polymorphic', // or 'strict'
    'allowed_tenant_models' => [
        App\Models\User::class,
        App\Models\Company::class,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'store' => null, // Use default cache store
    ],
];
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

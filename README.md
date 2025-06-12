# laravel-setanjo - Multi-Tenant Laravel Settings Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/run-tests?label=tests)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)

A powerful Laravel package for managing application settings with multi-tenant support. Store global settings or tenant-specific configurations with automatic type casting, caching, and a clean API. Perfect for A/B testing, feature flags, and user preferences.

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

1. **Install the package:**

```bash
composer require ahs12/laravel-setanjo
```

2. **Publish and run migrations:**

```bash
php artisan vendor:publish --tag="setanjo-migrations"
php artisan migrate
```

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

// Values are returned with correct types
$isActive = Settings::get('is_active'); // Returns boolean true
```

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

## Environment Variables

You can configure the package using environment variables:

```env
SETANJO_TENANCY_MODE=strict
SETANJO_STRICT_TENANT_MODEL="App\Models\User"
SETANJO_CACHE_ENABLED=true
SETANJO_CACHE_TTL=3600
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

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

## Requirements
- PHP 8.2 or higher
- Laravel 10.0 or higher

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
Settings::set('config', '{"theme": "dark", "lang": "en"}'); // JSON string
Settings::set('metadata',['version' => '1.0', 'author' => 'John']); // Object

// Values are returned with correct types
$isActive = Settings::get('is_active'); // Returns boolean true
$maxUsers = Settings::get('max_users'); // Returns integer 100
$rate = Settings::get('rate');          // Returns float 4.99
$features = Settings::get('features');  // Returns array ['api', 'sms']
$config = Settings::get('config');      // Returns array ['theme' => 'dark', 'lang' => 'en']
$metadata = Settings::get('metadata');  // Returns object with version and author properties
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

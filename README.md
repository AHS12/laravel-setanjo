# laravel-setanjo - Multi-Tenant Laravel Settings Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/run-tests?label=tests)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ahs12/laravel-setanjo/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/ahs12/laravel-setanjo/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ahs12/laravel-setanjo.svg?style=flat-square)](https://packagist.org/packages/ahs12/laravel-setanjo)

A powerful multi-tenant Laravel settings package that supports both strict and polymorphic tenancy modes with caching, validation, and a clean API.

## Features

- 🏢 **Multi-Tenant Support**: Both strict and polymorphic tenancy modes
- 🗃️ **Polymorphic Storage**: Store settings for any model type
- 🏛️ **Global Settings**: Settings without any tenant scope
- ⚡ **Caching**: Optional caching with configurable cache store
- 🔒 **Validation**: Validate tenant models and prevent unauthorized access
- 📦 **Clean API**: Simple, intuitive API inspired by popular packages
- 🧪 **Fully Tested**: Comprehensive test suite included
- ✅ **Type Safety**: Automatic type detection and conversion

## Installation

Install the package via Composer:

```bash
composer require ahs12/laravel-setanjo
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-setanjo-migrations"
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="laravel-setanjo-config"
```

## Quick Start

### Global Settings

```php
use Ahs12\laravel-setanjo\Facades\Settings;

// Set a global setting
Settings::set('app_name', 'My Application');

// Get a setting with default fallback
$appName = Settings::get('app_name', 'Default App Name');

// Check if setting exists
if (Settings::has('maintenance_mode')) {
    // Do something
}
```

### Tenant-Specific Settings

```php
// For a specific model instance
$company = Company::find(1);
Settings::for($company)->set('company_name', 'Acme Corp');
$companyName = Settings::for($company)->get('company_name');

// Using the model macro (if enabled)
$company->settings()->set('timezone', 'America/New_York');
$timezone = $company->settings()->get('timezone', 'UTC');

// For tenant by ID (useful in polymorphic mode)
Settings::forTenantId(1, Company::class)->set('setting', 'value');
```

### Data Types

laravel-setanjo automatically detects and handles different data types:

```php
Settings::set('string_setting', 'Hello World');
Settings::set('boolean_setting', true);
Settings::set('integer_setting', 42);
Settings::set('float_setting', 3.14);
Settings::set('array_setting', ['key' => 'value']);

// Values are automatically cast to their original types when retrieved
$bool = Settings::get('boolean_setting'); // Returns actual boolean true
$array = Settings::get('array_setting'); // Returns actual array
```

## Configuration

The package is highly configurable. Here are the key configuration options:

### Tenancy Modes

#### Polymorphic Mode (Default)
Allows multiple model types to have settings:

```php
// config/laravel-setanjo.php
'tenancy_mode' => 'polymorphic',
'allowed_tenant_models' => [
    App\Models\Company::class,
    App\Models\User::class,
    App\Models\Branch::class,
],
```

#### Strict Mode
Restricts to a single tenant model type:

```php
// config/laravel-setanjo.php
'tenancy_mode' => 'strict',
'strict_tenant_model' => App\Models\Company::class,
```

### Caching

Enable caching for better performance:

```php
// config/laravel-setanjo.php
'cache' => [
    'enabled' => true,
    'store' => null, // Use default cache store
    'prefix' => 'laravel-setanjo',
    'ttl' => 3600, // 1 hour
],
```

### Default Settings

Define default settings that can be installed via Artisan command:

```php
// config/laravel-setanjo.php
'defaults' => [
    'app_name' => [
        'value' => 'My Laravel App',
        'description' => 'Application name displayed to users',
    ],
    'maintenance_mode' => [
        'value' => false,
        'description' => 'Enable maintenance mode',
    ],
],
```

## Advanced Usage

### Multiple Operations

```php
// Chain multiple operations
Settings::for($company)
    ->set('name', 'Acme Corp')
    ->set('timezone', 'America/New_York')
    ->set('currency', 'USD');

// Get all settings for a tenant
$allSettings = Settings::for($company)->all();

// Remove specific setting
Settings::for($company)->forget('old_setting');

// Clear all settings for tenant
Settings::for($company)->flush();
```

### Working with Different Tenants

```php
$company = Company::find(1);
$user = User::find(1);

// Each tenant has isolated settings
Settings::for($company)->set('theme', 'dark');
Settings::for($user)->set('theme', 'light');

// Settings don't interfere with each other
echo Settings::for($company)->get('theme'); // 'dark'
echo Settings::for($user)->get('theme'); // 'light'
```

### Validation

The package can validate tenant models:

```php
// This will throw InvalidTenantException if User is not in allowed_tenant_models
Settings::for($user)->set('setting', 'value');

// Disable validation if needed
// config/laravel-setanjo.php
'validation' => [
    'enabled' => false,
    'throw_exceptions' => false,
],
```

## Artisan Commands

### Install Default Settings

```bash
php artisan laravel-setanjo:install-defaults
```

Install with force (overwrites existing):

```bash
php artisan laravel-setanjo:install-defaults --force
```

### Clear Settings Cache

```bash
php artisan laravel-setanjo:clear-cache
```

## Database Schema

The package creates a `settings` table with the following structure:

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key');
    $table->longText('value')->nullable();
    $table->text('description')->nullable();
    $table->string('type')->default('string');
    $table->nullableMorphs('tenantable'); // tenantable_type, tenantable_id
    $table->timestamps();
    
    // Indexes for performance
    $table->index(['key']);
    $table->index(['tenantable_type', 'tenantable_id']);
    $table->unique(['key', 'tenantable_type', 'tenantable_id']);
});
```

## Performance Considerations

- **Caching**: Enable caching in production for better performance
- **Indexing**: The package automatically creates database indexes for optimal queries
- **Lazy Loading**: Settings are loaded only when needed and cached in memory
- **Bulk Operations**: Use `all()` method instead of multiple `get()` calls when possible

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Your Name](https://github.com/yourusername)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Global Settings', function () {
    it('can set and get global settings', function () {
        Settings::set('app_name', 'Test App');

        expect(Settings::get('app_name'))->toBe('Test App');
    });

    it('returns default value when setting not found', function () {
        expect(Settings::get('nonexistent', 'default'))->toBe('default');
        expect(Settings::get('nonexistent'))->toBeNull();
    });

    it('can check if setting exists', function () {
        Settings::set('existing_key', 'value');

        expect(Settings::has('existing_key'))->toBeTrue();
        expect(Settings::has('nonexistent_key'))->toBeFalse();
    });

    it('can forget settings', function () {
        Settings::set('temporary', 'value');
        expect(Settings::has('temporary'))->toBeTrue();

        Settings::forget('temporary');
        expect(Settings::has('temporary'))->toBeFalse();
    });

    it('can get all settings', function () {
        Settings::set('key1', 'value1');
        Settings::set('key2', 'value2');

        $all = Settings::all();

        expect($all)->toHaveCount(2);
        expect($all['key1'])->toBe('value1');
        expect($all['key2'])->toBe('value2');
    });

    it('can flush all settings', function () {
        Settings::set('key1', 'value1');
        Settings::set('key2', 'value2');

        Settings::flush();

        expect(Settings::all())->toHaveCount(0);
    });
});

describe('Tenant Settings', function () {
    it('can handle tenant specific settings', function () {
        $company = Company::create(['name' => 'Acme Corp']);

        Settings::for($company)->set('company_setting', 'company_value');
        Settings::set('global_setting', 'global_value');

        expect(Settings::for($company)->get('company_setting'))->toBe('company_value');
        expect(Settings::get('global_setting'))->toBe('global_value');
        expect(Settings::get('company_setting'))->toBeNull(); // Global scope shouldn't see tenant setting
    });

    it('can handle multiple tenant types', function () {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        Settings::for($company)->set('setting', 'company_value');
        Settings::for($user)->set('setting', 'user_value');

        expect(Settings::for($company)->get('setting'))->toBe('company_value');
        expect(Settings::for($user)->get('setting'))->toBe('user_value');
    });

    it('can access tenant by id in polymorphic mode', function () {
        $company = Company::create(['name' => 'Acme Corp']);

        Settings::forTenantId($company->id, Company::class)->set('test', 'value');

        expect(Settings::for($company)->get('test'))->toBe('value');
        expect(Settings::forTenantId($company->id, Company::class)->get('test'))->toBe('value');
    });

    it('isolates settings between different tenants', function () {
        $company1 = Company::create(['name' => 'Company 1']);
        $company2 = Company::create(['name' => 'Company 2']);

        Settings::for($company1)->set('theme', 'dark');
        Settings::for($company2)->set('theme', 'light');

        expect(Settings::for($company1)->get('theme'))->toBe('dark');
        expect(Settings::for($company2)->get('theme'))->toBe('light');
    });

    it('can flush tenant specific settings', function () {
        $company = Company::create(['name' => 'Acme Corp']);

        Settings::for($company)->set('key1', 'value1');
        Settings::for($company)->set('key2', 'value2');
        Settings::set('global_key', 'global_value'); // Global setting

        Settings::for($company)->flush();

        expect(Settings::for($company)->all())->toHaveCount(0);
        expect(Settings::get('global_key'))->toBe('global_value'); // Global should remain
    });
});

describe('Data Types', function () {
    it('handles different data types correctly', function () {
        Settings::set('string_setting', 'string_value');
        Settings::set('boolean_setting', true);
        Settings::set('integer_setting', 42);
        Settings::set('float_setting', 3.14);
        Settings::set('array_setting', ['key' => 'value']);
        Settings::set('null_setting', null);

        expect(Settings::get('string_setting'))->toBeString()->toBe('string_value');
        expect(Settings::get('boolean_setting'))->toBeBool()->toBeTrue();
        expect(Settings::get('integer_setting'))->toBeInt()->toBe(42);
        expect(Settings::get('float_setting'))->toBeFloat()->toBe(3.14);
        expect(Settings::get('array_setting'))->toBeArray()->toBe(['key' => 'value']);
        expect(Settings::get('null_setting'))->toBeNull();
    });

    it('handles boolean false correctly', function () {
        Settings::set('false_setting', false);

        expect(Settings::get('false_setting'))->toBeBool()->toBeFalse();
        expect(Settings::has('false_setting'))->toBeTrue();
    });

    it('handles complex arrays', function () {
        $complexArray = [
            'nested' => [
                'key' => 'value',
                'number' => 123,
            ],
            'list' => [1, 2, 3, 4, 5],
        ];

        Settings::set('complex_array', $complexArray);

        expect(Settings::get('complex_array'))->toBe($complexArray);
    });
});

describe('Database Persistence', function () {
    it('persists settings to database', function () {
        Settings::set('persistent_key', 'persistent_value');

        $setting = Setting::where('key', 'persistent_key')->first();

        expect($setting)->not->toBeNull();
        expect($setting->key)->toBe('persistent_key');
        expect($setting->value)->toBe('persistent_value');
        expect($setting->type)->toBe(SettingType::STRING);
    });

    it('persists tenant settings with polymorphic relationship', function () {
        $company = Company::create(['name' => 'Test Company']);

        Settings::for($company)->set('tenant_key', 'tenant_value');

        $setting = Setting::where('key', 'tenant_key')->first();

        expect($setting)->not->toBeNull();
        expect($setting->tenantable_type)->toBe(Company::class);
        expect($setting->tenantable_id)->toBe($company->id);
        expect($setting->tenantable)->toBeInstanceOf(Company::class);
        expect($setting->tenantable->name)->toBe('Test Company');
    });

    it('updates existing settings', function () {
        Settings::set('update_key', 'original_value');

        $originalSetting = Setting::where('key', 'update_key')->first();
        $originalId = $originalSetting->id;

        Settings::set('update_key', 'updated_value');

        $updatedSetting = Setting::where('key', 'update_key')->first();

        expect($updatedSetting->id)->toBe($originalId); // Same record
        expect($updatedSetting->value)->toBe('updated_value');
    });
});

describe('Method Chaining', function () {
    it('supports method chaining for multiple operations', function () {
        $company = Company::create(['name' => 'Chain Corp']);

        $result = Settings::for($company)
            ->set('name', 'Acme Corp')
            ->set('timezone', 'America/New_York')
            ->set('currency', 'USD');

        expect($result)->toBeInstanceOf(\Ahs12\Setanjo\SetanjoManager::class);
        expect(Settings::for($company)->get('name'))->toBe('Acme Corp');
        expect(Settings::for($company)->get('timezone'))->toBe('America/New_York');
        expect(Settings::for($company)->get('currency'))->toBe('USD');
    });
});

describe('Edge Cases', function () {
    it('handles empty string values', function () {
        Settings::set('empty_string', '');

        expect(Settings::get('empty_string'))->toBe('');
        expect(Settings::has('empty_string'))->toBeTrue();
    });

    it('handles zero values', function () {
        Settings::set('zero_int', 0);
        Settings::set('zero_float', 0.0);

        expect(Settings::get('zero_int'))->toBe(0);
        expect(Settings::get('zero_float'))->toBe(0.0);
        expect(Settings::has('zero_int'))->toBeTrue();
        expect(Settings::has('zero_float'))->toBeTrue();
    });

    it('handles special characters in keys', function () {
        Settings::set('key.with.dots', 'dotted_value');
        Settings::set('key_with_underscores', 'underscore_value');
        Settings::set('key-with-dashes', 'dash_value');

        expect(Settings::get('key.with.dots'))->toBe('dotted_value');
        expect(Settings::get('key_with_underscores'))->toBe('underscore_value');
        expect(Settings::get('key-with-dashes'))->toBe('dash_value');
    });

    it('handles very long values', function () {
        $longValue = str_repeat('a', 10000);

        Settings::set('long_value', $longValue);

        expect(Settings::get('long_value'))->toBe($longValue);
    });
});

describe('Settings Count and Queries', function () {
    it('counts settings correctly', function () {
        $company = Company::create(['name' => 'Count Corp']);

        Settings::set('global1', 'value1');
        Settings::set('global2', 'value2');
        Settings::for($company)->set('tenant1', 'value1');
        Settings::for($company)->set('tenant2', 'value2');

        expect(Settings::all())->toHaveCount(2); // Only global
        expect(Settings::for($company)->all())->toHaveCount(2); // Only tenant
        expect(Setting::count())->toBe(4); // Total in database
    });
});

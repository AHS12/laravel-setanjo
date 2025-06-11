<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Exceptions\InvalidTenantException;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Repositories\DatabaseSettingsRepository;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SetanjoManager Unit Tests', function () {
    beforeEach(function () {
        $this->repository = new DatabaseSettingsRepository;
        $this->manager = new SetanjoManager($this->repository);
    });

    it('can be instantiated with repository', function () {
        expect($this->manager)->toBeInstanceOf(SetanjoManager::class);
    });

    it('returns new instance when setting tenant', function () {
        $company = Company::create(['name' => 'Test Company']);

        $instance = $this->manager->for($company);

        expect($instance)->toBeInstanceOf(SetanjoManager::class);
        expect($instance)->not->toBe($this->manager);
    });

    it('can get and set values', function () {
        $this->manager->set('test_key', 'test_value');

        $result = $this->manager->get('test_key');

        expect($result)->toBe('test_value');
    });

    it('returns default value when setting not found', function () {
        $result = $this->manager->get('nonexistent', 'default');

        expect($result)->toBe('default');
    });

    it('can check if setting exists', function () {
        $this->manager->set('existing_key', 'value');

        expect($this->manager->has('existing_key'))->toBeTrue();
        expect($this->manager->has('nonexistent'))->toBeFalse();
    });

    it('can forget settings', function () {
        $this->manager->set('temp_key', 'value');
        expect($this->manager->has('temp_key'))->toBeTrue();

        $this->manager->forget('temp_key');
        expect($this->manager->has('temp_key'))->toBeFalse();
    });

    it('can get all settings', function () {
        $this->manager->set('key1', 'value1');
        $this->manager->set('key2', 'value2');

        $all = $this->manager->all();

        expect($all)->toHaveCount(2);
        expect($all['key1'])->toBe('value1');
        expect($all['key2'])->toBe('value2');
    });

    it('can flush all settings', function () {
        $this->manager->set('key1', 'value1');
        $this->manager->set('key2', 'value2');

        $this->manager->flush();

        expect($this->manager->all())->toHaveCount(0);
    });

    it('supports method chaining', function () {
        $result = $this->manager
            ->set('key1', 'value1')
            ->set('key2', 'value2')
            ->forget('key1');

        expect($result)->toBe($this->manager);
        expect($this->manager->has('key1'))->toBeFalse();
        expect($this->manager->has('key2'))->toBeTrue();
    });

    it('handles tenant-specific settings', function () {
        $company = Company::create(['name' => 'Test Company']);

        // Set global setting
        $this->manager->set('global_key', 'global_value');

        // Set tenant setting
        $this->manager->for($company)->set('tenant_key', 'tenant_value');

        // Global scope should only see global settings
        expect($this->manager->get('global_key'))->toBe('global_value');
        expect($this->manager->get('tenant_key'))->toBeNull();

        // Tenant scope should see tenant settings
        expect($this->manager->for($company)->get('tenant_key'))->toBe('tenant_value');
    });

    it('isolates settings between different tenants', function () {
        $company1 = Company::create(['name' => 'Company 1']);
        $company2 = Company::create(['name' => 'Company 2']);

        $this->manager->for($company1)->set('same_key', 'company1_value');
        $this->manager->for($company2)->set('same_key', 'company2_value');

        expect($this->manager->for($company1)->get('same_key'))->toBe('company1_value');
        expect($this->manager->for($company2)->get('same_key'))->toBe('company2_value');
    });

    it('validates tenant when polymorphic mode is enabled', function () {
        config(['setanjo.tenancy_mode' => 'polymorphic']);
        config(['setanjo.allowed_tenant_models' => [Company::class]]);

        $company = Company::create(['name' => 'Test Company']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);

        expect(fn () => $this->manager->for($company))->not->toThrow(InvalidTenantException::class);
        expect(fn () => $this->manager->for($user))->toThrow(InvalidTenantException::class);
    });

    it('creates tenant by id in polymorphic mode', function () {
        config(['setanjo.tenancy_mode' => 'polymorphic']);
        config(['setanjo.allowed_tenant_models' => [Company::class]]);

        $company = Company::create(['name' => 'Test Company']);

        $this->manager->forTenantId($company->id, Company::class)->set('test_key', 'test_value');

        $result = $this->manager->for($company)->get('test_key');

        expect($result)->toBe('test_value');
    });

    it('throws exception when tenant not found by id', function () {
        config(['setanjo.tenancy_mode' => 'polymorphic']);
        config(['setanjo.allowed_tenant_models' => [Company::class]]);

        expect(fn () => $this->manager->forTenantId(999, Company::class))
            ->toThrow(InvalidTenantException::class, 'Tenant not found: Ahs12\Setanjo\Tests\Models\Company#999');
    });

    it('throws exception when model class not provided in polymorphic mode', function () {
        config(['setanjo.tenancy_mode' => 'polymorphic']);
        config(['setanjo.allowed_tenant_models' => [Company::class]]);

        expect(fn () => $this->manager->forTenantId(1))
            ->toThrow(InvalidTenantException::class, 'Model class required in polymorphic mode');
    });

    it('validates model class in allowed list for forTenantId', function () {
        config(['setanjo.tenancy_mode' => 'polymorphic']);
        config(['setanjo.allowed_tenant_models' => [Company::class]]);

        expect(fn () => $this->manager->forTenantId(1, User::class))
            ->toThrow(InvalidTenantException::class);
    });

    it('works when validation is disabled', function () {
        config(['setanjo.validation.enabled' => false]);

        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);

        $this->manager->for($user)->set('test_key', 'user_value');

        $result = $this->manager->for($user)->get('test_key');

        expect($result)->toBe('user_value');
    });

    it('handles strict mode validation', function () {
        config(['setanjo.tenancy_mode' => 'strict']);
        config(['setanjo.strict_tenant_model' => Company::class]);

        $company = Company::create(['name' => 'Test Company']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);

        expect(fn () => $this->manager->for($company))->not->toThrow(InvalidTenantException::class);
        expect(fn () => $this->manager->for($user))->toThrow(InvalidTenantException::class);
    });

    it('persists settings to database', function () {
        $this->manager->set('db_key', 'db_value');

        $setting = Setting::where('key', 'db_key')->first();

        expect($setting)->not->toBeNull();
        expect($setting->key)->toBe('db_key');
        expect($setting->value)->toBe('db_value');
    });

    it('persists tenant settings with polymorphic relationship', function () {
        $company = Company::create(['name' => 'Test Company']);

        $this->manager->for($company)->set('tenant_key', 'tenant_value');

        $setting = Setting::where('key', 'tenant_key')->first();

        expect($setting)->not->toBeNull();
        expect($setting->tenantable_type)->toBe(Company::class);
        expect($setting->tenantable_id)->toBe($company->id);
        expect($setting->tenantable)->toBeInstanceOf(Company::class);
        expect($setting->tenantable->name)->toBe('Test Company');
    });

    it('updates existing settings', function () {
        $this->manager->set('update_key', 'original_value');

        $originalSetting = Setting::where('key', 'update_key')->first();
        $originalId = $originalSetting->id;

        $this->manager->set('update_key', 'updated_value');

        $updatedSetting = Setting::where('key', 'update_key')->first();

        expect($updatedSetting->id)->toBe($originalId); // Same record
        expect($updatedSetting->value)->toBe('updated_value');
        expect(Setting::where('key', 'update_key')->count())->toBe(1); // Only one record
    });

    it('handles different data types', function () {
        $this->manager->set('string_val', 'test');
        $this->manager->set('int_val', 123);
        $this->manager->set('float_val', 12.34);
        $this->manager->set('bool_val', true);
        $this->manager->set('array_val', ['key' => 'value']);
        $this->manager->set('null_val', null);

        expect($this->manager->get('string_val'))->toBe('test');
        expect($this->manager->get('int_val'))->toBe(123);
        expect($this->manager->get('float_val'))->toBe(12.34);
        expect($this->manager->get('bool_val'))->toBe(true);
        expect($this->manager->get('array_val'))->toBe(['key' => 'value']);
        expect($this->manager->get('null_val'))->toBeNull();
    });

    it('handles empty and zero values correctly', function () {
        $this->manager->set('empty_string', '');
        $this->manager->set('zero_int', 0);
        $this->manager->set('zero_float', 0.0);
        $this->manager->set('false_bool', false);

        expect($this->manager->get('empty_string'))->toBe('');
        expect($this->manager->get('zero_int'))->toBe(0);
        expect($this->manager->get('zero_float'))->toBe(0.0);
        expect($this->manager->get('false_bool'))->toBe(false);

        expect($this->manager->has('empty_string'))->toBeTrue();
        expect($this->manager->has('zero_int'))->toBeTrue();
        expect($this->manager->has('zero_float'))->toBeTrue();
        expect($this->manager->has('false_bool'))->toBeTrue();
    });

    it('flushes only tenant-specific settings', function () {
        $company = Company::create(['name' => 'Test Company']);

        // Set global and tenant settings
        $this->manager->set('global_key', 'global_value');
        $this->manager->for($company)->set('tenant_key', 'tenant_value');

        // Flush only tenant settings
        $this->manager->for($company)->flush();

        // Global should remain, tenant should be gone
        expect($this->manager->get('global_key'))->toBe('global_value');
        expect($this->manager->for($company)->get('tenant_key'))->toBeNull();
        expect($this->manager->for($company)->all())->toHaveCount(0);
        expect($this->manager->all())->toHaveCount(1);
    });

    it('handles complex tenant scenarios', function () {
        $company1 = Company::create(['name' => 'Company 1']);
        $company2 = Company::create(['name' => 'Company 2']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);

        // Set settings for different tenants
        $this->manager->set('global_setting', 'global');
        $this->manager->for($company1)->set('company_setting', 'company1');
        $this->manager->for($company2)->set('company_setting', 'company2');
        $this->manager->for($user)->set('user_setting', 'user');

        // Verify isolation
        expect($this->manager->all())->toHaveCount(1);
        expect($this->manager->for($company1)->all())->toHaveCount(1);
        expect($this->manager->for($company2)->all())->toHaveCount(1);
        expect($this->manager->for($user)->all())->toHaveCount(1);

        expect(Setting::count())->toBe(4); // Total in database
    });
});

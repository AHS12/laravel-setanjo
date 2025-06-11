<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Repositories\DatabaseSettingsRepository;
use Ahs12\Setanjo\Tests\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('DatabaseSettingsRepository', function () {
    beforeEach(function () {
        $this->repository = new DatabaseSettingsRepository;
        config(['setanjo.cache.enabled' => false]); // Disable cache for unit tests
        // Clear any existing cache/state
        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('loadedSettings');
        $property->setAccessible(true);
        $property->setValue($this->repository, []);
    });

    it('can get setting value', function () {
        Setting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
        ]);

        $result = $this->repository->get('test_key');

        expect($result)->toBe('test_value');
    });

    it('returns default when setting not found', function () {
        $result = $this->repository->get('nonexistent', 'default_value');

        expect($result)->toBe('default_value');
    });

    it('can set setting value', function () {
        $this->repository->set('new_key', 'new_value');

        $setting = Setting::where('key', 'new_key')->first();

        expect($setting)->not->toBeNull();
        expect($setting->value)->toBe('new_value');
    });

    it('updates existing setting', function () {
        Setting::create([
            'key' => 'existing_key',
            'value' => 'old_value',
            'type' => 'string',
        ]);

        $this->repository->set('existing_key', 'new_value');

        $setting = Setting::where('key', 'existing_key')->first();
        expect($setting->value)->toBe('new_value');
        expect(Setting::where('key', 'existing_key')->count())->toBe(1);
    });

    it('can check if setting exists', function () {
        Setting::create([
            'key' => 'existing_key',
            'value' => 'value',
            'type' => 'string',
        ]);

        expect($this->repository->has('existing_key'))->toBeTrue();
        expect($this->repository->has('nonexistent'))->toBeFalse();
    });

    it('can forget setting', function () {
        Setting::create([
            'key' => 'temp_key',
            'value' => 'value',
            'type' => 'string',
        ]);

        $this->repository->forget('temp_key');

        expect(Setting::where('key', 'temp_key')->exists())->toBeFalse();
    });

    it('can get all settings', function () {
        Setting::create(['key' => 'key1', 'value' => 'value1', 'type' => 'string']);
        Setting::create(['key' => 'key2', 'value' => 'value2', 'type' => 'string']);

        $result = $this->repository->all();

        expect($result)->toHaveCount(2);
        expect($result['key1'])->toBe('value1');
        expect($result['key2'])->toBe('value2');
    });

    it('can flush all settings', function () {
        Setting::create(['key' => 'key1', 'value' => 'value1', 'type' => 'string']);
        Setting::create(['key' => 'key2', 'value' => 'value2', 'type' => 'string']);

        $this->repository->flush();

        expect(Setting::count())->toBe(0);
    });

    it('handles tenant settings separately', function () {
        $company = Company::create(['name' => 'Test Company']);

        // Global setting
        $this->repository->set('global_key', 'global_value');

        // Tenant setting - use repository instead of direct model creation
        $this->repository->set('tenant_key', 'tenant_value', $company);

        // Verify what's in the database
        $tenantSetting = Setting::where('key', 'tenant_key')->first();

        // Global scope should only see global settings
        expect($this->repository->get('global_key'))->toBe('global_value');
        expect($this->repository->get('tenant_key'))->toBeNull();

        // Tenant scope should see tenant settings
        expect($this->repository->get('tenant_key', null, $company))->toBe('tenant_value');
    });

    it('isolates tenant settings', function () {
        $company1 = Company::create(['name' => 'Company 1']);
        $company2 = Company::create(['name' => 'Company 2']);

        $this->repository->set('same_key', 'company1_value', $company1);
        $this->repository->set('same_key', 'company2_value', $company2);

        expect($this->repository->get('same_key', null, $company1))->toBe('company1_value');
        expect($this->repository->get('same_key', null, $company2))->toBe('company2_value');
    });
});

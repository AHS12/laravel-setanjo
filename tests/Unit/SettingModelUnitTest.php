<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Tests\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Setting Model', function () {
    it('has correct fillable attributes', function () {
        $setting = new Setting;

        expect($setting->getFillable())->toBe([
            'key',
            'value',
            'description',
            'type',
        ]);
    });

    it('has correct casts', function () {
        $setting = new Setting;

        expect($setting->getCasts())->toMatchArray([
            'value' => 'string',
            'type' => SettingType::class,
        ]);
    });

    it('uses configured table name', function () {
        config(['setanjo.table' => 'custom_settings']);

        $setting = new Setting;

        expect($setting->getTable())->toBe('custom_settings');
    });

    it('uses default table name when not configured', function () {
        config(['setanjo.table' => null]);

        $setting = new Setting;

        expect($setting->getTable())->toBe('settings');
    });

    it('has polymorphic tenantable relationship', function () {
        $setting = new Setting;

        expect($setting->tenantable())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
    });

    it('sets type from value automatically', function () {
        $setting = new Setting;

        $setting->setTypeFromValue('string_value');
        expect($setting->type)->toBe(SettingType::STRING);

        $setting->setTypeFromValue(123);
        expect($setting->type)->toBe(SettingType::INTEGER);

        $setting->setTypeFromValue(12.34);
        expect($setting->type)->toBe(SettingType::FLOAT);

        $setting->setTypeFromValue(true);
        expect($setting->type)->toBe(SettingType::BOOLEAN);

        $setting->setTypeFromValue(['key' => 'value']);
        expect($setting->type)->toBe(SettingType::ARRAY);
    });
});

describe('Setting Value Handling', function () {
    it('handles string values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::STRING,
        ]);

        $setting->value = 'test_value';

        expect($setting->getAttributes()['value'])->toBe('test_value');
        expect($setting->value)->toBe('test_value');
    });

    it('handles boolean values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::BOOLEAN,
        ]);

        $setting->value = true;
        expect($setting->getAttributes()['value'])->toBe('1');
        expect($setting->value)->toBeTrue();

        $setting->value = false;
        expect($setting->getAttributes()['value'])->toBe('0');
        expect($setting->value)->toBeFalse();
    });

    it('handles integer values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::INTEGER,
        ]);

        $setting->value = 123;
        expect($setting->getAttributes()['value'])->toBe('123');
        expect($setting->value)->toBe(123);
    });

    it('handles float values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::FLOAT,
        ]);

        $setting->value = 12.34;
        expect($setting->getAttributes()['value'])->toBe('12.34');
        expect($setting->value)->toBe(12.34);
    });

    it('handles array values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::ARRAY,
        ]);

        $array = ['key' => 'value', 'number' => 123];
        $setting->value = $array;

        expect($setting->getAttributes()['value'])->toBe(json_encode($array));
        expect($setting->value)->toBe($array);
    });

    it('handles null values correctly', function () {
        $setting = new Setting([
            'key' => 'test_key',
            'type' => SettingType::STRING,
        ]);

        $setting->value = null;
        expect($setting->value)->toBeNull();
    });
});

describe('Setting Scopes', function () {
    it('scopes global settings correctly', function () {
        $query = Setting::query();
        $globalQuery = $query->global();

        expect($globalQuery->toSql())->toContain('where "tenantable_type" is null and "tenantable_id" is null');
    });

    it('scopes tenant settings correctly', function () {
        $company = new Company(['name' => 'Test Company']);
        $company->id = 1;

        $query = Setting::query();
        $tenantQuery = $query->forTenant($company);

        expect($tenantQuery->toSql())->toContain('where "tenantable_type" = ? and "tenantable_id" = ?');
    });

    it('scopes to global when tenant is null', function () {
        $query = Setting::query();
        $tenantQuery = $query->forTenant(null);

        expect($tenantQuery->toSql())->toContain('where "tenantable_type" is null and "tenantable_id" is null');
    });
});

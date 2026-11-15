<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Tests\Fixtures\TestSetting;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
});

describe('Definition registry', function () {
    it('registers every case of a definition enum', function () {
        $registry = app(DefinitionRegistry::class);

        expect($registry->has('company_name'))->toBeTrue()
            ->and(array_keys($registry->features()))->toBe(['tax_feature', 'beta_feature'])
            ->and($registry->settings())->not->toHaveKey('tax_feature');
    });

    it('loads enum and inline array definitions from config', function () {
        config()->set('setanjo.definitions', [
            TestSetting::class,
            'site_name' => ['default' => 'My App', 'group' => 'general', 'meta' => ['help' => 'Shown in the header']],
            'new_checkout' => ['feature' => true],
        ]);

        $registry = new DefinitionRegistry;

        expect($registry->get('site_name')->default())->toBe('My App')
            ->and($registry->get('site_name')->type())->toBe(SettingType::STRING)
            ->and($registry->get('new_checkout')->isFeature())->toBeTrue()
            ->and($registry->get('new_checkout')->default())->toBeFalse()
            ->and($registry->get('new_checkout')->type())->toBe(SettingType::BOOLEAN)
            ->and($registry->has('retention_days'))->toBeTrue();
    });

    it('rejects enums that do not implement the contract', function () {
        app(DefinitionRegistry::class)->register(SettingType::class);
    })->throws(InvalidArgumentException::class);

    it('defines settings and features at runtime', function () {
        Settings::define('runtime_key', ['default' => 10]);
        Settings::defineFeature('runtime_flag');

        expect(Settings::get('runtime_key'))->toBe(10)
            ->and(Settings::feature('runtime_flag')->enabled())->toBeFalse();
    });

    it('accepts enum cases as keys', function () {
        Settings::set(TestSetting::COMPANY_NAME, 'Globex');

        expect(Settings::get(TestSetting::COMPANY_NAME))->toBe('Globex')
            ->and(Settings::get('company_name'))->toBe('Globex');
    });
});

describe('Defaults and type pinning', function () {
    it('returns the definition default when nothing is stored', function () {
        expect(Settings::get('retention_days'))->toBe(180)
            ->and(Settings::get('company_name'))->toBe('Acme')
            ->and(Settings::has('company_name'))->toBeFalse();
    });

    it('prefers an explicit default over the definition default', function () {
        expect(Settings::get('retention_days', 7))->toBe(7);
    });

    it('coerces form input to the defined type', function () {
        Settings::set('retention_days', '90');
        Settings::set('auto_approve', 'true');

        expect(Settings::get('retention_days'))->toBe(90)
            ->and(Settings::get('auto_approve'))->toBeTrue()
            ->and(Setting::global()->where('key', 'retention_days')->first()->type)->toBe(SettingType::INTEGER);
    });

    it('keeps numeric looking strings as strings for string definitions', function () {
        Settings::set('phone', '01814751026');

        expect(Settings::get('phone'))->toBe('01814751026')
            ->and(Setting::global()->where('key', 'phone')->first()->type)->toBe(SettingType::STRING);
    });

    it('rejects values that cannot be coerced', function () {
        Settings::set('auto_approve', 'maybe');
    })->throws(InvalidSettingValueException::class);

    it('still infers the type for undefined keys', function () {
        Settings::set('undefined_number', 42);

        expect(Setting::global()->where('key', 'undefined_number')->first()->type)->toBe(SettingType::INTEGER);
    });
});

describe('Validation', function () {
    it('applies the definition rules', function () {
        Settings::set('company_name', str_repeat('x', 51));
    })->throws(InvalidSettingValueException::class, 'company_name');

    it('enforces meta min and max', function () {
        expect(fn () => Settings::set('retention_days', 0))->toThrow(InvalidSettingValueException::class)
            ->and(fn () => Settings::set('retention_days', 5000))->toThrow(InvalidSettingValueException::class);

        Settings::set('retention_days', 30);
        expect(Settings::get('retention_days'))->toBe(30);
    });

    it('enforces meta options', function () {
        Settings::set('frequency', 'weekly');
        expect(Settings::get('frequency'))->toBe('weekly');

        Settings::set('frequency', 'hourly');
    })->throws(InvalidSettingValueException::class);

    it('exposes validation errors', function () {
        try {
            Settings::set('retention_days', 0);
        } catch (InvalidSettingValueException $e) {
            expect($e->getSettingKey())->toBe('retention_days')
                ->and($e->errors())->toHaveKey('value');

            return;
        }

        $this->fail('Expected an exception.');
    });

    it('can turn value validation off', function () {
        config()->set('setanjo.validation.values', false);

        Settings::set('retention_days', 0);

        expect(Settings::get('retention_days'))->toBe(0);
    });

    it('limits overrides to the allowed scope classes', function () {
        $company = Company::create(['name' => 'Acme']);
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        Settings::for($company)->set('company_only', 'yes');
        expect(Settings::for($company)->get('company_only'))->toBe('yes');

        Settings::for($user)->set('company_only', 'no');
    })->throws(InvalidSettingValueException::class, 'cannot be overridden');
});

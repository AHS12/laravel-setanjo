<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Events\SettingMetaUpdated;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Tests\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
});

describe('Setting meta', function () {
    it('returns definition meta without any stored row', function () {
        expect(Settings::meta('retention_days'))->toBe(['unit' => 'days', 'min' => 1, 'max' => 3650])
            ->and(Settings::meta('retention_days', 'unit'))->toBe('days')
            ->and(Settings::meta('retention_days', 'missing', 'x'))->toBe('x');
    });

    it('merges stored meta over definition meta', function () {
        Settings::setMeta('retention_days', ['unit' => 'd', 'help' => 'Days to keep']);

        expect(Settings::meta('retention_days'))->toBe([
            'unit' => 'd',
            'min' => 1,
            'max' => 3650,
            'help' => 'Days to keep',
        ]);
    });

    it('setMeta merges while replaceMeta overwrites', function () {
        Settings::setMeta('company_name', ['help' => 'Legal name']);
        Settings::setMeta('company_name', ['placeholder' => 'Acme Ltd']);

        expect(Settings::meta('company_name'))->toBe(['help' => 'Legal name', 'placeholder' => 'Acme Ltd']);

        Settings::replaceMeta('company_name', ['tags' => ['company']]);
        expect(Settings::meta('company_name'))->toBe(['tags' => ['company']]);

        Settings::replaceMeta('company_name', null);
        expect(Settings::meta('company_name'))->toBe([]);
    });

    it('stores anything json serialisable', function () {
        Settings::setMeta('anything', ['nested' => ['a' => [1, 2, 3]], 'flag' => true, 'ratio' => 0.5]);

        expect(Settings::meta('anything', 'nested.a'))->toBe([1, 2, 3])
            ->and(Settings::meta('anything', 'flag'))->toBeTrue();
    });

    it('creates the row from the definition default when the key has no value yet', function () {
        Settings::setMeta('retention_days', ['unit' => 'days']);

        $row = Setting::global()->where('key', 'retention_days')->first();

        expect($row->value)->toBe(180)
            ->and($row->meta)->toBe(['unit' => 'days'])
            ->and($row->meta('unit'))->toBe('days');
    });

    it('keeps the stored value when meta changes', function () {
        Settings::set('retention_days', 30);
        Settings::setMeta('retention_days', ['unit' => 'days']);

        expect(Settings::get('retention_days'))->toBe(30);
    });

    it('can be passed while setting a value', function () {
        Settings::set('site_title', 'Hello', meta: ['input' => 'text']);

        expect(Settings::meta('site_title', 'input'))->toBe('text');
    });

    it('belongs to the key, not to a scope', function () {
        $company = Company::create(['name' => 'Acme']);

        Settings::for($company)->setMeta('retention_days', ['unit' => 'weeks']);

        expect(Settings::meta('retention_days', 'unit'))->toBe('weeks')
            ->and(Setting::forTenant($company)->count())->toBe(0);
    });

    it('feeds meta bounds stored in the database into validation', function () {
        Settings::setMeta('retention_days', ['max' => 10]);

        Settings::set('retention_days', 11);
    })->throws(InvalidSettingValueException::class);

    it('rejects meta larger than the configured limit', function () {
        config()->set('setanjo.meta.max_bytes', 20);

        Settings::setMeta('company_name', ['help' => str_repeat('x', 50)]);
    })->throws(InvalidSettingValueException::class, 'bytes');

    it('dispatches an event with old and new meta', function () {
        Event::fake([SettingMetaUpdated::class]);

        Settings::setMeta('company_name', ['a' => 1]);
        Settings::setMeta('company_name', ['b' => 2]);

        Event::assertDispatched(
            SettingMetaUpdated::class,
            fn (SettingMetaUpdated $event) => $event->oldMeta === ['a' => 1] && $event->newMeta === ['a' => 1, 'b' => 2]
        );
    });

    it('is part of describe()', function () {
        Settings::set('retention_days', 45);

        expect(Settings::describe('retention_days'))->toMatchArray([
            'key' => 'retention_days',
            'defined' => true,
            'label' => 'Retention Days',
            'type' => 'integer',
            'group' => 'audit',
            'default' => 180,
            'value' => 45,
            'source' => 'global',
            'meta' => ['unit' => 'days', 'min' => 1, 'max' => 3650],
        ]);
    });
});

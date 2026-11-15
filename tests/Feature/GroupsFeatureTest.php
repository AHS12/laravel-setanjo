<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\GroupAction;
use Ahs12\Setanjo\Events\SettingGroupChanged;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
});

describe('Settings groups', function () {
    it('creates groups with a slug and next sort order', function () {
        $general = Settings::groups()->create(['name' => 'General Settings', 'icon' => 'cog']);
        $mail = Settings::groups()->create(['name' => 'Mail']);

        expect($general->slug)->toBe('general-settings')
            ->and($general->icon)->toBe('cog')
            ->and($mail->sort_order)->toBeGreaterThan($general->sort_order);
    });

    it('rejects duplicate slugs', function () {
        Settings::groups()->create(['name' => 'Mail']);
        Settings::groups()->create(['name' => 'mail']);
    })->throws(InvalidArgumentException::class);

    it('finds groups by id or slug', function () {
        $group = Settings::groups()->create(['name' => 'Mail']);

        expect(Settings::groups()->find($group->id)->is($group))->toBeTrue()
            ->and(Settings::groups()->find('mail')->is($group))->toBeTrue()
            ->and(Settings::groups()->find((string) $group->id)->is($group))->toBeTrue()
            ->and(Settings::groups()->find('nope'))->toBeNull();
    });

    it('updates groups', function () {
        Settings::groups()->create(['name' => 'Mail']);

        $group = Settings::groups()->update('mail', ['name' => 'Email', 'slug' => 'email', 'meta' => ['color' => 'blue']]);

        expect($group->name)->toBe('Email')
            ->and($group->slug)->toBe('email')
            ->and($group->meta)->toBe(['color' => 'blue']);
    });

    it('reorders groups', function () {
        $a = Settings::groups()->create(['name' => 'A']);
        $b = Settings::groups()->create(['name' => 'B']);
        $c = Settings::groups()->create(['name' => 'C']);

        Settings::groups()->reorder([$c->id, 'a', $b]);

        expect(Settings::groups()->all()->pluck('slug')->all())->toBe(['c', 'a', 'b']);
    });

    it('assigns settings and keeps their value', function () {
        Settings::set('company_name', 'Globex');
        $group = Settings::groups()->create(['name' => 'Company']);

        Settings::groups()->assign('company_name', 'company');

        expect(Settings::get('company_name'))->toBe('Globex')
            ->and(Setting::global()->where('key', 'company_name')->first()->group->is($group))->toBeTrue()
            ->and(Settings::describe('company_name')['group_id'])->toBe($group->id);
    });

    it('creates the row from the default when assigning a key that only exists in code', function () {
        Settings::groups()->create(['name' => 'Audit']);

        Settings::groups()->assign('retention_days', 'audit');

        expect(Setting::global()->where('key', 'retention_days')->first()->value)->toBe(180);
    });

    it('orders settings inside a group (drag and drop)', function () {
        Settings::groups()->create(['name' => 'Company']);

        Settings::groups()->reorderSettings('company', ['phone', 'company_name', 'frequency']);
        expect(Settings::groups()->keys('company'))->toBe(['phone', 'company_name', 'frequency']);

        Settings::groups()->reorderSettings('company', ['frequency', 'phone', 'company_name']);
        expect(Settings::groups()->keys('company'))->toBe(['frequency', 'phone', 'company_name']);
    });

    it('moves a setting between groups', function () {
        Settings::groups()->create(['name' => 'Company']);
        Settings::groups()->create(['name' => 'Other']);
        Settings::groups()->reorderSettings('company', ['phone', 'company_name']);

        Settings::groups()->assign('phone', 'other', 0);

        expect(Settings::groups()->keys('company'))->toBe(['company_name'])
            ->and(Settings::groups()->keys('other'))->toBe(['phone']);
    });

    it('ungroups settings when a group is deleted', function () {
        Settings::groups()->create(['name' => 'Company']);
        Settings::groups()->assign('company_name', 'company');

        Settings::groups()->delete('company');

        expect(SettingGroup::count())->toBe(0)
            ->and(Setting::global()->where('key', 'company_name')->first()->group_id)->toBeNull()
            ->and(Settings::groups()->keys(null))->toContain('company_name');
    });

    it('dispatches group events', function () {
        Event::fake([SettingGroupChanged::class]);

        $group = Settings::groups()->create(['name' => 'Company']);
        Settings::groups()->assign('company_name', $group);
        Settings::groups()->delete($group);

        Event::assertDispatched(SettingGroupChanged::class, fn ($e) => $e->action === GroupAction::CREATED);
        Event::assertDispatched(SettingGroupChanged::class, fn ($e) => $e->action === GroupAction::ASSIGNED && $e->context['key'] === 'company_name');
        Event::assertDispatched(SettingGroupChanged::class, fn ($e) => $e->action === GroupAction::DELETED);
    });

    it('uses a custom groups table', function () {
        config()->set('setanjo.groups_table', 'settings_group');

        expect((new SettingGroup)->getTable())->toBe('settings_group');
    });
});

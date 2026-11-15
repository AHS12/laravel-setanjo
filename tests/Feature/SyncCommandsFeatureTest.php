<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Events\SettingsSynced;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('setanjo:sync', function () {
    it('warns when nothing is defined', function () {
        $this->artisan('setanjo:sync')
            ->expectsOutput('No setting definitions registered. Add them to setanjo.definitions.')
            ->assertExitCode(0);
    });

    it('creates missing global rows, groups and descriptions', function () {
        registerTestDefinitions();

        $this->artisan('setanjo:sync')->assertExitCode(0);

        $row = Setting::global()->where('key', 'retention_days')->first();

        expect(Setting::global()->count())->toBe(6)
            ->and(Feature::count())->toBe(2)
            ->and($row->value)->toBe(180)
            ->and($row->type)->toBe(SettingType::INTEGER)
            ->and($row->description)->toBe('How long audit entries are kept')
            ->and($row->group->slug)->toBe('audit')
            ->and(SettingGroup::query()->pluck('name', 'slug')->all())->toBe(['company' => 'Company', 'audit' => 'Audit']);
    });

    it('is idempotent and keeps edited values', function () {
        registerTestDefinitions();
        $this->artisan('setanjo:sync');
        Settings::set('retention_days', 30);

        $this->artisan('setanjo:sync')->expectsOutput('Settings synced: 0 added, 0 updated, 0 pruned.');

        expect(Settings::get('retention_days'))->toBe(30);
    });

    it('does not move settings a user regrouped unless asked', function () {
        registerTestDefinitions();
        $this->artisan('setanjo:sync');
        Settings::groups()->create(['name' => 'Elsewhere']);
        Settings::groups()->assign('retention_days', 'elsewhere');

        $this->artisan('setanjo:sync');
        expect(Settings::groups()->keys('elsewhere'))->toBe(['retention_days']);

        $this->artisan('setanjo:sync --reset-groups');
        expect(Settings::groups()->keys('audit'))->toBe(['retention_days']);
    });

    it('resets stored meta on request', function () {
        registerTestDefinitions();
        $this->artisan('setanjo:sync');
        Settings::setMeta('retention_days', ['unit' => 'weeks']);

        $this->artisan('setanjo:sync');
        expect(Settings::meta('retention_days', 'unit'))->toBe('weeks');

        $this->artisan('setanjo:sync --reset-meta');
        expect(Settings::meta('retention_days', 'unit'))->toBe('days');
    });

    it('reports type mismatches and converts them with --force-types', function () {
        Settings::set('retention_days', '45'); // stored as string before definitions existed
        registerTestDefinitions();

        $this->artisan('setanjo:sync')
            ->expectsOutputToContain('Type mismatch: retention_days')
            ->assertExitCode(0);

        $this->artisan('setanjo:sync --force-types')->assertExitCode(0);

        $row = Setting::global()->where('key', 'retention_days')->first();
        expect($row->type)->toBe(SettingType::INTEGER)
            ->and($row->value)->toBe(45);
    });

    it('prunes keys without a definition across all scopes', function () {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('old_key', 'x');
        Settings::for($user)->set('old_key', 'y');
        registerTestDefinitions();

        $this->artisan('setanjo:sync --prune --dry-run')->expectsOutput('Prune: old_key');
        expect(Setting::where('key', 'old_key')->count())->toBe(2);

        $this->artisan('setanjo:sync --prune');
        expect(Setting::where('key', 'old_key')->count())->toBe(0);
    });

    it('writes nothing on a dry run', function () {
        registerTestDefinitions();

        $this->artisan('setanjo:sync --dry-run')
            ->expectsOutput('DRY RUN COMPLETE: would add 8, update 0, prune 0.')
            ->assertExitCode(0);

        expect(Setting::count())->toBe(0)
            ->and(SettingGroup::count())->toBe(0);
    });

    it('dispatches SettingsSynced', function () {
        registerTestDefinitions();
        Event::fake([SettingsSynced::class]);

        $this->artisan('setanjo:sync');

        Event::assertDispatched(SettingsSynced::class, fn (SettingsSynced $e) => count($e->added) === 8);
    });
});

describe('setanjo:export and setanjo:import', function () {
    it('round-trips values, types, meta, groups and scopes', function () {
        registerTestDefinitions();
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::groups()->create(['name' => 'Audit', 'icon' => 'shield']);
        Settings::set('retention_days', 90);
        Settings::setMeta('retention_days', ['unit' => 'days']);
        Settings::groups()->assign('retention_days', 'audit', 3);
        Settings::for($user)->set('retention_days', 30);
        Settings::set('payload', ['a' => [1, 2]]);
        Settings::set('json_string', '{"x":1}');

        $path = tempnam(sys_get_temp_dir(), 'setanjo').'.json';
        $this->artisan('setanjo:export', ['path' => $path])->assertExitCode(0);

        Setting::query()->delete();
        SettingGroup::query()->delete();
        Settings::flushCache();

        $this->artisan('setanjo:import', ['path' => $path])->assertExitCode(0);

        $row = Setting::global()->where('key', 'retention_days')->first();

        expect(Settings::get('retention_days'))->toBe(90)
            ->and(Settings::for($user)->get('retention_days'))->toBe(30)
            ->and(Settings::get('payload'))->toBe(['a' => [1, 2]])
            ->and(Settings::get('json_string'))->toBe('{"x":1}')
            ->and(Settings::meta('retention_days', 'unit'))->toBe('days')
            ->and($row->group->slug)->toBe('audit')
            ->and($row->group->icon)->toBe('shield')
            ->and($row->sort_order)->toBe(3);

        @unlink($path);
    });

    it('updates existing rows instead of duplicating them', function () {
        Settings::set('site', 'one');
        $path = tempnam(sys_get_temp_dir(), 'setanjo').'.json';
        $this->artisan('setanjo:export', ['path' => $path, '--global' => true]);

        Settings::set('site', 'two');
        $this->artisan('setanjo:import', ['path' => $path]);

        expect(Setting::where('key', 'site')->count())->toBe(1)
            ->and(Settings::get('site'))->toBe('one');

        @unlink($path);
    });

    it('exports a single scope', function () {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('global_key', 'g');
        Settings::for($user)->set('user_key', 'u');

        $path = tempnam(sys_get_temp_dir(), 'setanjo').'.json';
        $this->artisan('setanjo:export', ['path' => $path, '--scope' => User::class.':'.$user->id])->assertExitCode(0);

        $payload = json_decode(file_get_contents($path), true);
        expect(array_column($payload['settings'], 'key'))->toBe(['user_key']);

        @unlink($path);
    });

    it('fails on a missing or invalid file', function () {
        $this->artisan('setanjo:import', ['path' => 'missing.json'])->assertExitCode(1);

        $path = tempnam(sys_get_temp_dir(), 'setanjo');
        file_put_contents($path, '{nope');
        $this->artisan('setanjo:import', ['path' => $path])->assertExitCode(1);
        @unlink($path);
    });
});

describe('setanjo:clear-cache', function () {
    it('rejects an invalid tenant format instead of exiting the process', function () {
        config()->set('setanjo.cache.enabled', true);

        $this->artisan('setanjo:clear-cache', ['--tenant' => 'invalid-format'])->assertExitCode(1);
    });

    it('accepts shells that strip backslashes', function () {
        config()->set('setanjo.cache.enabled', true);

        $this->artisan('setanjo:clear-cache', ['--tenant' => 'AppModelsUser:1'])
            ->expectsOutput('Cleared cache for tenant: App\\Models\\User:1')
            ->assertExitCode(0);
    });

    it('clears every scope with --all', function () {
        config()->set('setanjo.cache.enabled', true);
        Settings::set('k', 'v');
        Settings::get('k');

        $this->artisan('setanjo:clear-cache --all')->expectsOutput('Cleared all setanjo cache.')->assertExitCode(0);
    });
});

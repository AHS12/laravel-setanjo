<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

describe('Clear Cache Command', function () {
    test('it can clear cache', function () {
        $this->artisan('setanjo:clear-cache')
            ->assertExitCode(0);
    });

    test('it can clear cache when disabled', function () {
        config()->set('setanjo.cache.enabled', false);

        $this->artisan('setanjo:clear-cache')
            ->expectsOutput('Cache is disabled. Nothing to clear.')
            ->assertExitCode(0);
    });

    test('it can clear tenant specific cache', function () {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        Settings::for($user)->set('user_setting', 'user_value');

        $this->artisan('setanjo:clear-cache', [
            '--tenant' => 'Ahs12\\Setanjo\\Tests\\Models\\User:'.$user->id,
        ])
            ->assertExitCode(0);
    });

    test('it can clear all cache', function () {
        Settings::set('global_setting', 'global_value');

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Settings::for($user)->set('user_setting', 'user_value');

        $this->artisan('setanjo:clear-cache --all')
            ->assertExitCode(0);
    });

    test('it handles invalid tenant format', function () {
        $this->artisan('setanjo:clear-cache', ['--tenant' => 'invalid-format'])
            ->assertExitCode(0);
    });
});

describe('Install Defaults Command', function () {
    test('it can install default settings', function () {
        config()->set('setanjo.defaults', [
            'app_name' => [
                'value' => 'Test App',
            ],
            'theme' => 'dark',
            'max_users' => [
                'value' => 100,
            ],
        ]);

        $this->artisan('setanjo:install-defaults')
            ->expectsOutput('Installed setting: app_name')
            ->expectsOutput('Installed setting: theme')
            ->expectsOutput('Installed setting: max_users')
            ->assertExitCode(0);

        expect(Settings::get('app_name'))->toBe('Test App');
        expect(Settings::get('theme'))->toBe('dark');
        expect(Settings::get('max_users'))->toBe(100);
    });

    test('it can force reinstall default settings', function () {
        Settings::set('app_name', 'Old Name');
        Settings::set('theme', 'light');

        config()->set('setanjo.defaults', [
            'app_name' => [
                'value' => 'New Name',
            ],
            'theme' => 'dark',
        ]);

        $this->artisan('setanjo:install-defaults --force')
            ->expectsOutput('Installed setting: app_name')
            ->expectsOutput('Installed setting: theme')
            ->assertExitCode(0);

        expect(Settings::get('app_name'))->toBe('New Name');
        expect(Settings::get('theme'))->toBe('dark');
    });

    test('it skips existing settings without force', function () {
        Settings::set('app_name', 'Existing App');

        config()->set('setanjo.defaults', [
            'app_name' => [
                'value' => 'New App',
            ],
            'theme' => 'dark',
        ]);

        $this->artisan('setanjo:install-defaults')
            ->expectsOutput('Skipped existing setting: app_name')
            ->expectsOutput('Installed setting: theme')
            ->assertExitCode(0);

        expect(Settings::get('app_name'))->toBe('Existing App');
        expect(Settings::get('theme'))->toBe('dark');
    });

    test('it handles empty defaults configuration', function () {
        config()->set('setanjo.defaults', []);

        $this->artisan('setanjo:install-defaults')
            ->expectsOutput('No default settings configured.')
            ->assertExitCode(0);
    });

    test('it handles missing defaults configuration', function () {
        Config::set('setanjo.defaults', null);

        $this->artisan('setanjo:install-defaults')
            ->expectsOutput('No default settings configured.')
            ->assertExitCode(0);
    });

    test('it shows installation summary', function () {
        Settings::set('existing_setting', 'value');

        config()->set('setanjo.defaults', [
            'existing_setting' => 'new_value',
            'new_setting' => 'value',
            'another_setting' => 'another_value',
        ]);

        $this->artisan('setanjo:install-defaults')
            ->expectsOutput('Installation complete! Installed: 2, Skipped: 1')
            ->assertExitCode(0);
    });

    test('it handles different default value formats', function () {
        config()->set('setanjo.defaults', [
            'simple_value' => 'simple',
            'complex_value' => [
                'value' => 'complex',
            ],
            'boolean_value' => true,
            'numeric_value' => 42,
        ]);

        $this->artisan('setanjo:install-defaults')
            ->assertExitCode(0);

        expect(Settings::get('simple_value'))->toBe('simple');
        expect(Settings::get('complex_value'))->toBe('complex');
        expect(Settings::get('boolean_value'))->toBeTrue();
        expect(Settings::get('numeric_value'))->toBe(42);
    });
});

describe('Commands Integration', function () {
    test('commands work together', function () {
        config()->set('setanjo.defaults', [
            'test_setting' => 'test_value',
        ]);

        $this->artisan('setanjo:install-defaults')
            ->assertExitCode(0);

        expect(Settings::get('test_setting'))->toBe('test_value');

        $this->artisan('setanjo:clear-cache')
            ->assertExitCode(0);

        expect(Settings::get('test_setting'))->toBe('test_value');
    });

    test('install defaults then clear specific tenant cache', function () {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        config()->set('setanjo.defaults', [
            'user_theme' => 'blue',
        ]);

        $this->artisan('setanjo:install-defaults')
            ->assertExitCode(0);

        Settings::for($user)->set('user_preference', 'dark_mode');

        $this->artisan('setanjo:clear-cache', [
            '--tenant' => 'Ahs12\\Setanjo\\Tests\\Models\\User:'.$user->id,
        ])
            ->assertExitCode(0);

        expect(Settings::get('user_theme'))->toBe('blue');
        expect(Settings::for($user)->get('user_preference'))->toBe('dark_mode');
    });

    test('force install after cache operations', function () {
        config()->set('setanjo.defaults', [
            'original_setting' => 'original_value',
        ]);

        $this->artisan('setanjo:install-defaults')
            ->assertExitCode(0);

        $this->artisan('setanjo:clear-cache')
            ->assertExitCode(0);

        config()->set('setanjo.defaults', [
            'original_setting' => 'updated_value',
        ]);

        $this->artisan('setanjo:install-defaults --force')
            ->expectsOutput('Installed setting: original_setting')
            ->assertExitCode(0);

        expect(Settings::get('original_setting'))->toBe('updated_value');
    });
});

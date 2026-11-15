<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Mockery;

uses(RefreshDatabase::class);

describe('v2 regressions', function () {
    it('reports a stored null value as existing', function () {
        Settings::set('nullable', null);

        expect(Settings::has('nullable'))->toBeTrue()
            ->and(Settings::get('nullable', 'fallback'))->toBeNull();
    });

    it('can change the type of an existing key', function () {
        Settings::set('mixed', 5);
        Settings::set('mixed', [1, 2]);

        expect(Settings::get('mixed'))->toBe([1, 2])
            ->and(Setting::global()->where('key', 'mixed')->first()->type->value)->toBe('array');
    });

    it('can change an array key back to a scalar', function () {
        Settings::set('mixed', ['a' => 1]);
        Settings::set('mixed', true);

        expect(Settings::get('mixed'))->toBeTrue();
    });

    it('drops in-memory values between queue jobs so workers see changes from other processes', function () {
        Settings::set('worker_value', 'old');
        expect(Settings::get('worker_value'))->toBe('old');

        // another process changes the row directly
        Setting::global()->where('key', 'worker_value')->update(['value' => 'new']);
        expect(Settings::get('worker_value'))->toBe('old');

        $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
        $job->shouldReceive('payload')->andReturn([]);

        event(new JobProcessing('sync', $job));

        expect(Settings::get('worker_value'))->toBe('new');
    });

    it('binds the repository as a singleton shared with the manager', function () {
        expect(app(SettingsRepositoryInterface::class))->toBe(Settings::repository());
    });
});

<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\ValueSource;
use Ahs12\Setanjo\Events\FeatureRulesChanged;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Setanjo;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    $this->user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
});

afterEach(function () {
    Setanjo::flush();
});

describe('Scheduled flags', function () {
    beforeEach(function () {
        $this->travelTo(Carbon::parse('2026-12-01 10:00:00'));
        Settings::feature('tax_feature')->enable()->schedule('2026-12-05 09:00', '2026-12-10 00:00');
    });

    it('is only on inside its window', function () {
        $flag = fn () => Settings::feature('tax_feature')->for($this->user);

        expect($flag()->enabled())->toBeFalse()
            ->and($flag()->explain()->source)->toBe(ValueSource::SCHEDULE)
            ->and($flag()->rules()->window())->toBe('pending');

        $this->travelTo(Carbon::parse('2026-12-05 09:00:00'));
        expect($flag()->enabled())->toBeTrue();

        $this->travelTo(Carbon::parse('2026-12-10 00:00:00'));
        expect($flag()->enabled())->toBeFalse()
            ->and($flag()->rules()->window())->toBe('ended');
    });

    it('does not turn on a flag that is off, and lets a record override win', function () {
        $this->travelTo(Carbon::parse('2026-12-06'));
        Settings::feature('tax_feature')->disable();

        expect(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeFalse();

        $this->travelTo(Carbon::parse('2026-12-01'));
        Settings::feature('tax_feature')->for($this->user)->enable();

        expect(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeTrue();
    });

    it('allows open ends and rejects an end before the start', function () {
        Settings::feature('tax_feature')->schedule(null, '2026-12-02');
        expect(Settings::feature('tax_feature')->enabled())->toBeTrue();

        Settings::feature('tax_feature')->schedule('2026-12-03', '2026-12-02');
    })->throws(InvalidSettingValueException::class, 'after its start');
});

describe('Flag dependencies', function () {
    it('is off while a required flag is off for the same subject', function () {
        Settings::feature('beta_feature')->requires('tax_feature');

        $resolved = Settings::feature('beta_feature')->for($this->user)->explain();
        expect($resolved->value)->toBeFalse()
            ->and($resolved->source)->toBe(ValueSource::DEPENDENCY)
            ->and($resolved->detail)->toBe('tax_feature');

        Settings::feature('tax_feature')->for($this->user)->enable();

        expect(Settings::feature('beta_feature')->for($this->user)->enabled())->toBeTrue()
            ->and(Settings::feature('beta_feature')->enabled())->toBeFalse();
    });

    it('follows requirements through other flags', function () {
        Settings::feature('reports_v2')->enable();
        Settings::feature('tax_feature')->enable()->requires('reports_v2');
        Settings::feature('beta_feature')->requires(['tax_feature']);

        expect(Settings::feature('beta_feature')->enabled())->toBeTrue();

        Settings::feature('reports_v2')->disable();

        expect(Settings::feature('beta_feature')->explain()->detail)->toBe('tax_feature');
    });

    it('rejects itself, unknown flags and cycles', function (string $key, array $requires, string $message) {
        Settings::feature('reports_v2')->enable()->requires('beta_feature');
        Settings::feature('beta_feature')->requires('tax_feature');

        expect(fn () => Settings::feature($key)->requires($requires))
            ->toThrow(InvalidSettingValueException::class, $message);
    })->with([
        'itself' => ['tax_feature', ['tax_feature'], 'cannot require itself'],
        'unknown' => ['tax_feature', ['nope'], 'no feature flag [nope]'],
        'cycle' => ['tax_feature', ['reports_v2'], '[reports_v2] already requires [tax_feature]'],
    ]);
});

describe('Percentage rollout', function () {
    it('gives the flag to the records whose bucket is below the percentage', function () {
        $users = collect(range(1, 40))->map(fn ($i) => User::query()->create(['name' => "U{$i}", 'email' => "u{$i}@example.com"]));
        Settings::feature('beta_feature')->rollout(30);

        foreach ($users as $user) {
            $expected = SetanjoManager::rolloutBucket('beta_feature', $user) < 30;
            $resolved = Settings::feature('beta_feature')->for($user)->explain();

            expect($resolved->value)->toBe($expected)
                ->and($resolved->source)->toBe(ValueSource::ROLLOUT);
        }

        $on = fn () => $users->filter(fn (User $user) => Settings::feature('beta_feature')->for($user)->enabled());
        $before = $on()->pluck('id');
        Settings::feature('beta_feature')->rollout(60);

        // Raising the percentage keeps everyone who already had the flag.
        expect($before->diff($on()->pluck('id')))->toBeEmpty()
            ->and($on()->count())->toBeGreaterThan($before->count());
    });

    it('is all or nothing at 100 and 0, and off without a subject', function () {
        Settings::feature('beta_feature')->rollout(0);
        expect(Settings::feature('beta_feature')->for($this->user)->enabled())->toBeFalse();

        Settings::feature('beta_feature')->rollout(100);
        expect(Settings::feature('beta_feature')->for($this->user)->enabled())->toBeTrue()
            ->and(Settings::feature('beta_feature')->explain()->source)->toBe(ValueSource::DEFAULT);

        Settings::feature('beta_feature')->rollout(50);
        expect(Settings::feature('beta_feature')->enabled())->toBeFalse();
    });

    it('rejects a percentage outside 0-100', function () {
        Settings::feature('beta_feature')->rollout(101);
    })->throws(InvalidSettingValueException::class, 'between 0 and 100');
});

describe('Archived flags', function () {
    it('reads as off, even with an override, until restored', function () {
        Settings::feature('beta_feature')->for($this->user)->enable();
        Settings::feature('beta_feature')->archive();

        expect(Settings::feature('beta_feature')->for($this->user)->enabled())->toBeFalse()
            ->and(Settings::feature('beta_feature')->explain()->source)->toBe(ValueSource::ARCHIVED)
            ->and(Settings::feature('beta_feature')->for($this->user)->override())->toBeTrue();

        Settings::feature('beta_feature')->restore();

        expect(Settings::feature('beta_feature')->for($this->user)->enabled())->toBeTrue();
    });
});

describe('Rule changes', function () {
    it('fires one event per real change', function () {
        config()->set('setanjo.history.enabled', true);
        Event::fake([FeatureRulesChanged::class]);

        Settings::feature('beta_feature')->rollout(25);
        Settings::feature('beta_feature')->rollout(25);

        Event::assertDispatchedTimes(FeatureRulesChanged::class, 1);
    });

    it('writes old and new rules to history', function () {
        config()->set('setanjo.history.enabled', true);
        Settings::feature('beta_feature')->rollout(25);

        $entry = SettingHistory::query()->sole();

        expect($entry->action)->toBe('rules')
            ->and($entry->old_meta['rollout'])->toBeNull()
            ->and($entry->new_meta['rollout'])->toBe(25);
    });

    it('survives an export and import', function () {
        Settings::feature('tax_feature')->enable()->requires('beta_feature');
        Settings::feature('beta_feature')->rollout(40)->schedule('2026-01-01 00:00', null);
        $path = storage_path('framework/testing/rules-export.json');
        File::ensureDirectoryExists(dirname($path));

        Artisan::call('setanjo:export', ['path' => $path]);
        Settings::feature('beta_feature')->rollout(null)->schedule(null);
        Settings::feature('tax_feature')->requires(null);
        Artisan::call('setanjo:import', ['path' => $path]);
        Settings::flushCache();

        expect(Settings::feature('beta_feature')->rules()->rollout)->toBe(40)
            ->and(Settings::feature('beta_feature')->rules()->enabledFrom?->format('Y-m-d H:i'))->toBe('2026-01-01 00:00')
            ->and(Settings::feature('tax_feature')->rules()->requires)->toBe(['beta_feature']);

        File::delete($path);
    });
});

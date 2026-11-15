<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();

    $this->company = Company::create(['name' => 'Acme']);
    $this->user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
});

describe('Feature tables', function () {
    it('stores flags outside the settings table', function () {
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->for($this->user)->disable();

        $feature = Feature::where('key', 'tax_feature')->first();

        expect(Setting::count())->toBe(0)
            ->and($feature->enabled)->toBeTrue()
            ->and($feature->name)->toBe('Tax Feature')
            ->and($feature->overrides)->toHaveCount(1)
            ->and($feature->overrides->first()->scope_type)->toBe(User::class)
            ->and($feature->overrides->first()->enabled)->toBeFalse();
    });

    it('keeps one override per feature and scope', function () {
        Settings::feature('tax_feature')->for($this->user)->enable();
        Settings::feature('tax_feature')->for($this->user)->disable();
        Settings::feature('tax_feature')->for($this->user)->enable();

        expect(FeatureOverride::count())->toBe(1);
    });

    it('clears the global state on global inherit so the default applies', function () {
        Settings::feature('beta_feature')->disable();
        expect(Settings::feature('beta_feature')->enabled())->toBeFalse();

        Settings::feature('beta_feature')->inherit();

        expect(Feature::where('key', 'beta_feature')->first()->enabled)->toBeNull()
            ->and(Settings::feature('beta_feature')->enabled())->toBeTrue()
            ->and(Settings::feature('beta_feature')->override())->toBeNull();
    });

    it('refuses set() on a feature key', function () {
        Settings::set('tax_feature', true);
    })->throws(InvalidSettingValueException::class, 'is a feature flag');

    it('counts overrides per feature', function () {
        Settings::feature('tax_feature')->for($this->user)->enable();
        Settings::feature('tax_feature')->for($this->company)->disable();
        Settings::feature('beta_feature')->for($this->user)->disable();

        expect(Settings::featureRepository()->overrideCounts())->toBe(['beta_feature' => 1, 'tax_feature' => 2])
            ->and(Settings::featureRepository()->overridesOf('tax_feature'))->toHaveCount(2);
    });

    it('lists flags that only exist in the database', function () {
        Settings::feature('db_only_flag')->enable();

        expect(Settings::features()->all())->toBe([
            'tax_feature' => false,
            'beta_feature' => true,
            'db_only_flag' => true,
        ]);
    });

    it('resolves a three level chain in one query', function () {
        Settings::resolveScopesUsing(fn ($subject) => [$subject, $this->company]);
        Settings::feature('tax_feature')->for($this->company)->enable();
        Settings::featureRepository()->reset();

        $count = function (callable $callback): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $callback();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$result, $queries];
        };

        // an override in the chain answers with one query for every scope's overrides
        [$enabled, $queries] = $count(fn () => Settings::feature('tax_feature')->for($this->user)->enabled());
        expect($enabled)->toBeTrue()->and($queries)->toBe(1);

        // falling through to global adds one query for the flag rows
        Settings::featureRepository()->reset();
        [$enabled, $queries] = $count(fn () => Settings::feature('beta_feature')->for($this->user)->enabled());
        expect($enabled)->toBeTrue()->and($queries)->toBe(2);
    });

    it('deletes overrides with the flag', function () {
        Settings::feature('tax_feature')->for($this->user)->enable();

        Settings::featureRepository()->delete('tax_feature');

        expect(Feature::count())->toBe(0)->and(FeatureOverride::count())->toBe(0);
    });

    it('caches flags and overrides', function () {
        config()->set('setanjo.cache.enabled', true);
        config()->set('cache.default', 'array');

        Settings::feature('tax_feature')->for($this->user)->enable();
        Settings::feature('tax_feature')->for($this->user)->enabled();
        Settings::featureRepository()->reset();
        Settings::feature('tax_feature')->for($this->user)->enabled();
        Settings::featureRepository()->reset();

        DB::enableQueryLog();
        expect(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeTrue();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(0);
    });
});

describe('Feature meta and describe', function () {
    it('merges definition meta with stored meta', function () {
        Settings::feature('tax_feature')->setMeta(['owner' => 'finance', 'tags' => ['tax']]);

        expect(Settings::feature('tax_feature')->meta('owner'))->toBe('finance')
            ->and(Feature::where('key', 'tax_feature')->first()->meta('tags'))->toBe(['tax']);

        Settings::feature('tax_feature')->replaceMeta(null);
        expect(Settings::feature('tax_feature')->meta())->toBe([]);
    });

    it('describes a flag', function () {
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->for($this->user)->disable();

        expect(Settings::feature('tax_feature')->for($this->user)->describe())->toMatchArray([
            'key' => 'tax_feature',
            'defined' => true,
            'label' => 'Tax Feature',
            'default' => false,
            'global' => true,
            'enabled' => false,
            'source' => 'scope',
            'scope_type' => User::class,
            'override_count' => 1,
        ]);
    });
});

describe('Feature groups', function () {
    it('assigns and orders flags in groups', function () {
        Settings::groups()->create(['name' => 'Finance']);

        Settings::groups()->reorderFeatures('finance', ['beta_feature', 'tax_feature']);

        expect(Settings::groups()->featureKeys('finance'))->toBe(['beta_feature', 'tax_feature']);

        Settings::groups()->delete('finance');

        expect(Settings::groups()->featureKeys(null))->toBe(['beta_feature', 'tax_feature']);
    });
});

describe('Feature history', function () {
    it('records toggles', function () {
        config()->set('setanjo.history.enabled', true);

        Settings::feature('tax_feature')->for($this->user)->enable();
        Settings::feature('tax_feature')->for($this->user)->inherit();

        expect(SettingHistory::query()->orderBy('id')->get(['setting_key', 'action', 'new_value', 'scope_type'])->toArray())->toBe([
            ['setting_key' => 'tax_feature', 'action' => 'toggle', 'new_value' => 'true', 'scope_type' => User::class],
            ['setting_key' => 'tax_feature', 'action' => 'toggle', 'new_value' => null, 'scope_type' => User::class],
        ]);
    });
});

describe('Moving flags out of the settings table', function () {
    beforeEach(function () {
        // flags as v2 / MEM stored them: boolean rows in `settings`
        $column = 'tenantable';
        DB::table('settings')->insert([
            ['key' => 'tax_feature', 'value' => '1', 'type' => 'boolean', "{$column}_type" => null, "{$column}_id" => null],
            ['key' => 'tax_feature', 'value' => '0', 'type' => 'boolean', "{$column}_type" => User::class, "{$column}_id" => $this->user->id],
            ['key' => 'company_name', 'value' => 'Acme', 'type' => 'string', "{$column}_type" => null, "{$column}_id" => null],
        ]);
    });

    it('warns during sync and leaves the rows alone when pruning', function () {
        $this->artisan('setanjo:sync --prune')
            ->expectsOutputToContain('Run setanjo:migrate-feature-flags')
            ->assertExitCode(0);

        expect(Setting::where('key', 'tax_feature')->count())->toBe(2);
    });

    it('moves global and scoped rows into the feature tables', function () {
        $this->artisan('setanjo:migrate-feature-flags --dry-run')->expectsOutput('Move: tax_feature (2 rows)');
        expect(Feature::count())->toBe(0);

        $this->artisan('setanjo:migrate-feature-flags')->assertExitCode(0);

        expect(Setting::where('key', 'tax_feature')->count())->toBe(0)
            ->and(Settings::get('company_name'))->toBe('Acme')
            ->and(Settings::feature('tax_feature')->enabled())->toBeTrue()
            ->and(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeFalse()
            ->and(Settings::feature('tax_feature')->for($this->user)->override())->toBeFalse();
    });

    it('can keep the old rows', function () {
        $this->artisan('setanjo:migrate-feature-flags --keep');

        expect(Setting::where('key', 'tax_feature')->count())->toBe(2)
            ->and(FeatureOverride::count())->toBe(1);
    });
});

describe('Feature sync, export and import', function () {
    it('prunes flags without a feature definition', function () {
        Settings::feature('removed_flag')->for($this->user)->enable();

        $this->artisan('setanjo:sync --prune')->expectsOutput('Prune feature: removed_flag');

        expect(Feature::where('key', 'removed_flag')->exists())->toBeFalse()
            ->and(FeatureOverride::count())->toBe(0);
    });

    it('round-trips flags and overrides', function () {
        Settings::groups()->create(['name' => 'Finance']);
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->setMeta(['owner' => 'finance']);
        Settings::groups()->assignFeature('tax_feature', 'finance');
        Settings::feature('tax_feature')->for($this->user)->disable();

        $path = tempnam(sys_get_temp_dir(), 'setanjo').'.json';
        $this->artisan('setanjo:export', ['path' => $path])->assertExitCode(0);

        FeatureOverride::query()->delete();
        Feature::query()->delete();
        Settings::flushCache();

        $this->artisan('setanjo:import', ['path' => $path])->assertExitCode(0);

        expect(Settings::feature('tax_feature')->enabled())->toBeTrue()
            ->and(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeFalse()
            ->and(Settings::feature('tax_feature')->meta('owner'))->toBe('finance')
            ->and(Settings::groups()->featureKeys('finance'))->toBe(['tax_feature']);

        @unlink($path);
    });
});

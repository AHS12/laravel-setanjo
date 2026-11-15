<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\ValueSource;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();

    $this->company = Company::create(['name' => 'Acme']);
    $this->user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    // user -> company -> global -> default
    Settings::resolveScopesUsing(fn ($subject) => $subject instanceof User ? [$subject, $this->company] : [$subject]);
});

describe('Scope chain resolution', function () {
    it('falls back to the definition default', function () {
        $resolved = Settings::for($this->user)->explain('retention_days');

        expect($resolved->value)->toBe(180)
            ->and($resolved->source)->toBe(ValueSource::DEFAULT)
            ->and($resolved->scope)->toBeNull();
    });

    it('uses the global value over the default', function () {
        Settings::set('retention_days', 90);

        expect(Settings::for($this->user)->effective('retention_days'))->toBe(90)
            ->and(Settings::for($this->user)->explain('retention_days')->source)->toBe(ValueSource::GLOBAL);
    });

    it('uses the closest scope in the chain', function () {
        Settings::set('retention_days', 90);
        Settings::for($this->company)->set('retention_days', 60);

        expect(Settings::for($this->user)->effective('retention_days'))->toBe(60);

        Settings::for($this->user)->set('retention_days', 30);

        $resolved = Settings::for($this->user)->explain('retention_days');
        expect($resolved->value)->toBe(30)
            ->and($resolved->source)->toBe(ValueSource::SCOPE)
            ->and($resolved->scope->is($this->user))->toBeTrue();
    });

    it('treats a stored false as an explicit value', function () {
        Settings::set('auto_approve', true);
        Settings::for($this->user)->set('auto_approve', false);

        expect(Settings::for($this->user)->effective('auto_approve'))->toBeFalse();
    });

    it('keeps get() scoped to the current tenant only', function () {
        Settings::set('retention_days', 90);

        expect(Settings::for($this->user)->get('retention_days'))->toBe(180)
            ->and(Settings::for($this->user)->has('retention_days'))->toBeFalse();
    });

    it('uses the authenticated user when no scope is given', function () {
        Settings::for($this->user)->set('retention_days', 30);

        expect(Settings::effective('retention_days'))->toBe(180);

        $this->actingAs($this->user);

        expect(Settings::effective('retention_days'))->toBe(30);
    });

    it('can change how the default subject is resolved', function () {
        Settings::for($this->company)->set('retention_days', 60);
        Settings::resolveDefaultSubjectUsing(fn () => $this->company);

        expect(Settings::effective('retention_days'))->toBe(60);
    });

    it('returns the explicit default for undefined keys', function () {
        expect(Settings::for($this->user)->effective('nope', 'fallback'))->toBe('fallback');
    });

    it('skips scopes the definition does not allow', function () {
        // company_only may only be overridden by companies; a stray user row is ignored
        Settings::for($this->company)->set('company_only', 'company');

        expect(Settings::for($this->user)->effective('company_only'))->toBe('company');
    });

    it('loads the whole chain in a single query', function () {
        Settings::set('retention_days', 90);
        Settings::for($this->company)->set('retention_days', 60);
        Settings::repository()->reset();

        DB::enableQueryLog();
        Settings::for($this->user)->effective('retention_days');
        Settings::for($this->user)->effective('company_name');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    });
});

describe('Cache', function () {
    beforeEach(function () {
        config()->set('setanjo.cache.enabled', true);
        config()->set('cache.default', 'array');
    });

    it('serves repeated loads from cache', function () {
        Settings::set('retention_days', 90);
        Settings::repository()->reset();
        Settings::get('retention_days');
        Settings::repository()->reset();

        DB::enableQueryLog();
        expect(Settings::get('retention_days'))->toBe(90);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(0);
    });

    it('invalidates the scope on write', function () {
        Settings::set('retention_days', 90);
        Settings::get('retention_days');

        Settings::set('retention_days', 45);
        Settings::repository()->reset();

        expect(Settings::get('retention_days'))->toBe(45);
    });

    it('flushCache invalidates every scope', function () {
        Settings::set('retention_days', 90);
        Settings::for($this->user)->set('retention_days', 30);

        // writes keep this process' memory current and drop the cache; reload to warm it
        Settings::repository()->reset();
        Settings::get('retention_days');
        Settings::for($this->user)->get('retention_days');

        DB::table('settings')->update(['value' => '10']);
        Settings::repository()->reset();
        expect(Settings::get('retention_days'))->toBe(90);

        Settings::flushCache();

        expect(Settings::get('retention_days'))->toBe(10)
            ->and(Settings::for($this->user)->get('retention_days'))->toBe(10);
    });

    it('forgetCache refreshes one scope', function () {
        Settings::set('retention_days', 90);
        Settings::get('retention_days');
        DB::table('settings')->update(['value' => '10']);

        Settings::forgetCache();

        expect(Settings::get('retention_days'))->toBe(10);
    });
});

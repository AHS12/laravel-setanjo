<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Support\ScopeKey;
use Ahs12\Setanjo\Tests\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    ScopeKey::flush();
});

it('knows a UUID cannot be in an integer id column but any numeric key can', function () {
    $uuid = '01a1e0a8-85f2-7820-b431-95b22ced939c';

    expect(ScopeKey::fits(new FeatureOverride, 'scope_id', 42))->toBeTrue()
        ->and(ScopeKey::fits(new FeatureOverride, 'scope_id', '42'))->toBeTrue()
        ->and(ScopeKey::fits(new FeatureOverride, 'scope_id', null))->toBeTrue()
        ->and(ScopeKey::fits(new FeatureOverride, 'scope_id', $uuid))->toBeFalse()
        ->and(ScopeKey::fits(new Setting, 'tenantable_id', $uuid))->toBeFalse();
});

it('reads a UUID-keyed scope against integer id columns without querying them', function () {
    config()->set('setanjo.allowed_tenant_models', [...config('setanjo.allowed_tenant_models'), Branch::class]);
    $branch = Branch::query()->create(['title' => 'Sylhet']);
    DB::enableQueryLog();

    expect(Settings::for($branch)->get('company_name'))->toBe('Acme')
        ->and(Settings::feature('beta_feature')->for($branch)->enabled())->toBeTrue()
        ->and(Settings::feature('beta_feature')->for($branch)->override())->toBeNull();

    // On Postgres comparing a UUID with a bigint column is an error, so it must never be sent.
    $sent = collect(DB::getQueryLog())->filter(fn (array $query) => in_array($branch->id, $query['bindings'], true));

    expect($sent)->toBeEmpty();
});

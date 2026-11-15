<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Http\Middleware\AcceptJson;
use Ahs12\Setanjo\Http\Middleware\Authorize;
use Ahs12\Setanjo\Http\Middleware\GuardDashboard;
use Ahs12\Setanjo\Setanjo;
use Ahs12\Setanjo\SetanjoServiceProvider;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    config()->set('auth.providers.users.model', User::class);
});

afterEach(function () {
    Setanjo::flush();
});

/**
 * Turn the public API on (routes are registered at boot, so register them again).
 *
 * @param  array<string, mixed>  $config
 */
function enableApi(array $config = []): void
{
    config()->set('setanjo.api.enabled', true);

    foreach ($config as $key => $value) {
        config()->set("setanjo.api.{$key}", $value);
    }

    (fn () => $this->registerApi())->call(app()->getProvider(SetanjoServiceProvider::class));
    app('router')->getRoutes()->refreshNameLookups();
}

describe('Public HTTP API', function () {
    it('is off by default', function () {
        expect(Route::has('setanjo.http.settings.index'))->toBeFalse();

        $this->getJson('/api/setanjo/settings')->assertNotFound();
    });

    it('serves the dashboard endpoints under its own prefix and middleware', function () {
        enableApi();

        $middleware = Route::getRoutes()->getByName('setanjo.http.settings.index')->gatherMiddleware();

        expect($middleware)->toBe([AcceptJson::class, 'api', Authorize::class, GuardDashboard::class])
            ->and(Route::has('setanjo.http.features.bulk'))->toBeTrue()
            ->and(Route::has('setanjo.http.history.revert'))->toBeTrue()
            ->and(Route::has('setanjo.http.dashboard'))->toBeFalse();
    });

    it('reads and writes without a session or CSRF token', function () {
        enableApi();
        Setanjo::auth(fn () => true);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->getJson('/api/setanjo/settings')->assertOk()->assertJsonStructure(['data' => [['key', 'value', 'type', 'source']]]);

        $this->put('/api/setanjo/settings/retention_days', ['value' => 90])
            ->assertOk()
            ->assertJsonPath('data.value', 90);

        $this->put('/api/setanjo/features/tax_feature', ['state' => true, 'scope_type' => 'user', 'scope_id' => $user->id])
            ->assertOk()
            ->assertJsonPath('data.override', true);

        expect(Settings::get('retention_days'))->toBe(90)
            ->and(Settings::feature('tax_feature')->for($user)->enabled())->toBeTrue();
    });

    it('shows one setting or flag, globally or as a record sees it', function () {
        enableApi();
        Setanjo::auth(fn () => true);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('frequency', 'weekly');
        Settings::for($user)->set('frequency', 'daily');

        $this->getJson('/api/setanjo/settings/frequency')
            ->assertOk()
            ->assertJsonPath('data.value', 'weekly')
            ->assertJsonPath('data.override_count', 1);

        $this->getJson("/api/setanjo/settings/frequency?scope_type=user&scope_id={$user->id}")
            ->assertOk()
            ->assertJsonPath('data.effective', 'daily')
            ->assertJsonPath('data.source', 'scope');

        $this->getJson('/api/setanjo/features/beta_feature')->assertOk()->assertJsonPath('data.enabled', true);
        $this->getJson('/api/setanjo/settings/nope')->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/setanjo/features/company_name')->assertNotFound();
    });

    it('answers JSON even when the client does not ask for it', function () {
        enableApi();

        $this->get('/api/setanjo/settings')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['message' => __('setanjo::dashboard.forbidden')]);

        Setanjo::auth(fn () => true);

        $this->put('/api/setanjo/settings/retention_days', ['value' => 0])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['value']]);
    });

    it('uses the app middleware for auth: 401 for guests, the app rule for users', function () {
        enableApi(['middleware' => ['api', 'auth', 'can:isAdmin']]);
        Gate::define('isAdmin', fn (User $user) => $user->email === 'admin@example.com');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@example.com']);

        $this->get('/api/setanjo/settings')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
        $this->actingAs($staff)->getJson('/api/setanjo/settings')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/setanjo/settings')->assertOk();
    });

    it('keeps readonly mode and the manage rules', function () {
        enableApi();
        Setanjo::auth(fn () => true);

        Setanjo::manage(fn () => false);
        $this->getJson('/api/setanjo/features')->assertOk();
        $this->putJson('/api/setanjo/features/tax_feature', ['state' => true])->assertForbidden();

        Setanjo::manage(null);
        config()->set('setanjo.dashboard.readonly', true);
        $this->putJson('/api/setanjo/settings/company_name', ['value' => 'Initech'])
            ->assertForbidden()
            ->assertJson(['message' => __('setanjo::dashboard.readonly')]);
    });

    it('works without the dashboard and under a custom prefix', function () {
        config()->set('setanjo.dashboard.enabled', false);
        enableApi(['prefix' => '/internal/settings-api/']);
        Setanjo::auth(fn () => true);

        $this->getJson('/internal/settings-api/overview')->assertOk()->assertJsonStructure(['data' => ['settings', 'features', 'scopes']]);
    });
});

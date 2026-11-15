<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Dashboard\Assets;
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
});

afterEach(function () {
    Setanjo::flush();
});

/**
 * Re-register the dashboard routes with another middleware list (routes are registered at boot).
 *
 * @param  array<int, string>  $middleware
 */
function dashboardMiddleware(array $middleware): void
{
    config()->set('setanjo.dashboard.middleware', $middleware);
    config()->set('auth.providers.users.model', User::class);

    (fn () => $this->registerDashboard())->call(app()->getProvider(SetanjoServiceProvider::class));
    app('router')->getRoutes()->refreshNameLookups();
}

describe('Dashboard access', function () {
    it('is denied outside the local environment by default', function () {
        $this->get('/setanjo')->assertForbidden();
        $this->getJson('/setanjo/api/settings')
            ->assertForbidden()
            ->assertJson(['message' => __('setanjo::dashboard.forbidden')]);
    });

    it('is allowed in the local environment', function () {
        app()->detectEnvironment(fn () => 'local');

        $this->get('/setanjo')->assertOk();
        $this->getJson('/setanjo/api/settings')->assertOk();
    });

    it('uses the viewSetanjo gate when the app defines it', function () {
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $guest = User::query()->create(['name' => 'Guest', 'email' => 'guest@example.com']);

        Gate::define('viewSetanjo', fn (User $user) => $user->email === 'admin@example.com');

        $this->actingAs($guest)->getJson('/setanjo/api/settings')->assertForbidden();
        $this->actingAs($admin)->getJson('/setanjo/api/settings')->assertOk();
    });

    it('uses the Setanjo::auth callback before anything else', function () {
        app()->detectEnvironment(fn () => 'local');
        Setanjo::auth(fn () => false);

        $this->getJson('/setanjo/api/settings')->assertForbidden();

        Setanjo::auth(fn () => true);
        app()->detectEnvironment(fn () => 'production');

        $this->getJson('/setanjo/api/settings')->assertOk();
    });

    it('blocks writes in readonly mode but still allows reads', function () {
        Setanjo::auth(fn () => true);
        config()->set('setanjo.dashboard.readonly', true);

        $this->getJson('/setanjo/api/settings')->assertOk();
        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])
            ->assertForbidden()
            ->assertJson(['message' => __('setanjo::dashboard.readonly')]);
    });

    it('uses the manageSetanjo gate for writes when the app defines it', function () {
        $user = User::query()->create(['name' => 'Support', 'email' => 'support@example.com']);
        Setanjo::auth(fn () => true);
        Gate::define('manageSetanjo', fn (User $user) => false);

        $this->actingAs($user)->getJson('/setanjo/api/settings')->assertOk();
        $this->actingAs($user)->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])->assertForbidden();
    });

    it('uses the Setanjo::manage callback for writes', function () {
        Setanjo::auth(fn () => true);
        Setanjo::manage(fn () => false);

        $this->putJson('/setanjo/api/features/tax_feature', ['state' => true])->assertForbidden();

        Setanjo::manage(fn () => true);

        $this->putJson('/setanjo/api/features/tax_feature', ['state' => true])->assertOk();
    });

    it('registers no routes when the dashboard is disabled', function () {
        config()->set('setanjo.dashboard.enabled', false);
        $before = count(Route::getRoutes()->getRoutes());

        (fn () => $this->registerDashboard())->call(app()->getProvider(SetanjoServiceProvider::class));

        expect(count(Route::getRoutes()->getRoutes()))->toBe($before);
    });

    it('lists Authorize in the default middleware and always adds the guard last', function () {
        expect(config('setanjo.dashboard.middleware'))->toBe(['web', Authorize::class]);

        $middleware = Route::getRoutes()->getByName('setanjo.api.settings.index')->gatherMiddleware();

        expect($middleware)->toContain('web', Authorize::class)
            ->and(end($middleware))->toBe(GuardDashboard::class);
    });

    it('lets the app middleware decide when Authorize is removed', function () {
        dashboardMiddleware(['web', 'auth', 'can:isAdmin']);
        Gate::define('isAdmin', fn (User $user) => $user->email === 'admin@example.com');
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@example.com']);

        // No viewSetanjo gate and not local: only the app's middleware decides.
        $this->getJson('/setanjo/api/settings')->assertUnauthorized();
        $this->actingAs($staff)->getJson('/setanjo/api/settings')->assertForbidden();
        $this->actingAs($admin)->getJson('/setanjo/api/settings')->assertOk();
        $this->actingAs($admin)->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])->assertOk();
        $this->actingAs($admin)->get('/setanjo')->assertOk();
    });

    it('still needs the gate when Authorize is kept next to the app middleware', function () {
        dashboardMiddleware(['web', 'auth', Authorize::class]);
        $user = User::query()->create(['name' => 'Staff', 'email' => 'staff@example.com']);

        $this->actingAs($user)->getJson('/setanjo/api/settings')->assertForbidden();

        Gate::define('viewSetanjo', fn (User $user) => true);

        $this->actingAs($user)->getJson('/setanjo/api/settings')->assertOk();
    });

    it('is never public outside local when only web is left', function () {
        dashboardMiddleware(['web']);

        $this->getJson('/setanjo/api/settings')
            ->assertForbidden()
            ->assertJson(['message' => __('setanjo::dashboard.unprotected')]);

        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $this->actingAs($user)->getJson('/setanjo/api/settings')->assertOk();
    });

    it('allows an unprotected stack locally or when require_auth is off', function () {
        dashboardMiddleware(['web']);

        app()->detectEnvironment(fn () => 'local');
        $this->getJson('/setanjo/api/settings')->assertOk();

        app()->detectEnvironment(fn () => 'production');
        config()->set('setanjo.dashboard.require_auth', false);
        $this->getJson('/setanjo/api/settings')->assertOk();
    });

    it('keeps readonly and the manage rules when Authorize is removed', function () {
        dashboardMiddleware(['web', 'auth']);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        Setanjo::manage(fn () => false);
        $this->actingAs($user)->getJson('/setanjo/api/settings')->assertOk();
        $this->actingAs($user)->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])->assertForbidden();

        Setanjo::manage(null);
        config()->set('setanjo.dashboard.readonly', true);
        $this->actingAs($user)->putJson('/setanjo/api/features/tax_feature', ['state' => true])
            ->assertForbidden()
            ->assertJson(['message' => __('setanjo::dashboard.readonly')]);
    });

    it('renders the page with the app config', function () {
        Setanjo::auth(fn () => true);

        $this->get('/setanjo/flags')
            ->assertOk()
            ->assertSee('setanjo-config', false)
            ->assertSee('"canManage":true', false);
    });

    it('inlines the prebuilt assets so nothing has to be published', function () {
        Setanjo::auth(fn () => true);

        expect(Assets::css())->not->toBeEmpty()
            ->and(Assets::js())->not->toBeEmpty()
            ->and(Assets::js())->not->toContain('</script');

        $this->get('/setanjo')
            ->assertOk()
            ->assertSee('x-data="setanjo"', false)
            ->assertSee(substr(Assets::css(), 0, 60), false);
    });
});

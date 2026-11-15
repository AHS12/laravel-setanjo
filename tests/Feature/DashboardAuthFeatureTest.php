<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Dashboard\Assets;
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

<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Enums\ValueSource;
use Ahs12\Setanjo\Events\FeatureToggled;
use Ahs12\Setanjo\Events\SettingUpdated;
use Ahs12\Setanjo\Exceptions\UndefinedFeatureException;
use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Tests\Fixtures\TestSetting;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();

    $this->company = Company::create(['name' => 'Acme']);
    $this->user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    Settings::resolveScopesUsing(fn ($subject) => $subject instanceof User ? [$subject, $this->company] : [$subject]);
});

describe('Feature API', function () {
    it('uses the definition default', function () {
        expect(Settings::feature('tax_feature')->enabled())->toBeFalse()
            ->and(Settings::feature(TestSetting::BETA_FEATURE)->enabled())->toBeTrue();
    });

    it('toggles globally', function () {
        Settings::feature('tax_feature')->enable();
        expect(Settings::feature('tax_feature')->enabled())->toBeTrue();

        Settings::feature('tax_feature')->disable();
        expect(Settings::feature('tax_feature')->disabled())->toBeTrue();
    });

    it('overrides per user and per tenant', function () {
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->for($this->company)->disable();

        expect(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeFalse();

        Settings::feature('tax_feature')->for($this->user)->enable();
        expect(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeTrue();
    });

    it('inherits again after removing an override', function () {
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->for($this->user)->disable();
        expect(Settings::feature('tax_feature')->for($this->user)->override())->toBeFalse();

        Settings::feature('tax_feature')->for($this->user)->inherit();

        expect(Settings::feature('tax_feature')->for($this->user)->override())->toBeNull()
            ->and(Settings::feature('tax_feature')->for($this->user)->enabled())->toBeTrue();
    });

    it('toggle() accepts true, false and null', function () {
        Settings::feature('tax_feature')->for($this->user)->toggle(true);
        expect(Settings::feature('tax_feature')->for($this->user)->override())->toBeTrue();

        Settings::feature('tax_feature')->for($this->user)->toggle(null);
        expect(Settings::feature('tax_feature')->for($this->user)->override())->toBeNull();
    });

    it('explains where the state comes from', function () {
        Settings::feature('tax_feature')->for($this->company)->enable();

        $resolved = Settings::feature('tax_feature')->for($this->user)->explain();

        expect($resolved->source)->toBe(ValueSource::SCOPE)
            ->and($resolved->scope->is($this->company))->toBeTrue();
    });

    it('lists every feature for a subject', function () {
        Settings::feature('tax_feature')->for($this->user)->enable();

        expect(Settings::features()->for($this->user)->all())->toBe([
            'tax_feature' => true,
            'beta_feature' => true,
        ])->and(Settings::features()->all())->toBe([
            'tax_feature' => false,
            'beta_feature' => true,
        ]);
    });

    it('resolves for the authenticated user by default', function () {
        Settings::feature('tax_feature')->for($this->user)->enable();
        $this->actingAs($this->user);

        expect(Settings::feature('tax_feature')->enabled())->toBeTrue();
    });

    it('allows undefined features unless strict', function () {
        expect(Settings::feature('not_defined')->enabled())->toBeFalse();

        config()->set('setanjo.features.strict', true);

        Settings::feature('not_defined');
    })->throws(UndefinedFeatureException::class);

    it('rejects non-feature keys in strict mode', function () {
        config()->set('setanjo.features.strict', true);

        Settings::feature('company_name');
    })->throws(UndefinedFeatureException::class);

    it('dispatches events', function () {
        Event::fake([FeatureToggled::class, SettingUpdated::class]);

        Settings::feature('tax_feature')->for($this->user)->enable();
        Settings::feature('tax_feature')->for($this->user)->inherit();
        Settings::feature('tax_feature')->for($this->user)->inherit(); // nothing left to remove

        Event::assertDispatched(FeatureToggled::class, fn (FeatureToggled $e) => $e->state === true && $e->scope->is($this->user));
        Event::assertDispatchedTimes(FeatureToggled::class, 2);
        Event::assertNotDispatched(SettingUpdated::class);
    });
});

describe('HasFeatures trait', function () {
    it('manages flags from the model', function () {
        $this->user->enableFeature('tax_feature');
        expect($this->user->hasFeature('tax_feature'))->toBeTrue()
            ->and($this->user->featureStates())->toBe(['tax_feature' => true, 'beta_feature' => true]);

        $this->user->disableFeature('tax_feature');
        expect($this->user->hasFeature('tax_feature'))->toBeFalse();

        $this->user->inheritFeature('tax_feature');
        expect($this->user->feature('tax_feature')->override())->toBeNull();
    });
});

describe('Middleware', function () {
    beforeEach(function () {
        Route::middleware('setanjo.feature:tax_feature')->get('/_tax', fn () => 'ok');
        Route::middleware('setanjo.feature:tax_feature,beta_feature')->get('/_both', fn () => 'ok');
    });

    it('blocks disabled features with a JSON 403', function () {
        $this->actingAs($this->user)
            ->getJson('/_tax')
            ->assertForbidden()
            ->assertJson(['feature' => 'tax_feature', 'message' => 'This feature is currently disabled.']);
    });

    it('passes enabled features for the user', function () {
        $this->user->enableFeature('tax_feature');

        $this->actingAs($this->user)->getJson('/_tax')->assertOk();
    });

    it('requires every listed feature', function () {
        $this->user->enableFeature('tax_feature');
        $this->user->disableFeature('beta_feature');

        $this->actingAs($this->user)->getJson('/_both')->assertForbidden()->assertJson(['feature' => 'beta_feature']);
    });

    it('uses global state for guests', function () {
        $this->getJson('/_tax')->assertForbidden();

        Settings::feature('tax_feature')->enable();

        $this->getJson('/_tax')->assertOk();
    });

    it('translates the message', function () {
        app()->setLocale('bn');

        $this->getJson('/_tax')->assertForbidden()->assertJson(['message' => 'এই ফিচারটি বর্তমানে বন্ধ আছে।']);
    });
});

describe('Blade', function () {
    it('renders @feature blocks', function () {
        $template = "@feature('tax_feature') on @else off @endfeature";

        expect(trim(Blade::render($template)))->toBe('off');

        Settings::feature('tax_feature')->enable();

        expect(trim(Blade::render($template)))->toBe('on');
    });

    it('renders @feature for an explicit subject', function () {
        $this->user->enableFeature('tax_feature');

        expect(trim(Blade::render("@feature('tax_feature', \$user) on @else off @endfeature", ['user' => $this->user])))->toBe('on');
    });

    it('renders @setting values escaped', function () {
        Settings::set('company_name', '<b>Acme</b>');

        expect(Blade::render("@setting('company_name')"))->toBe('&lt;b&gt;Acme&lt;/b&gt;')
            ->and(Blade::render("@setting('unknown_key', 'n/a')"))->toBe('n/a');
    });
});

describe('Gate integration', function () {
    it('is off by default', function () {
        Settings::feature('tax_feature')->enable();

        expect(Gate::forUser($this->user)->allows('feature:tax_feature'))->toBeFalse();
    });

    it('resolves feature abilities when enabled', function () {
        config()->set('setanjo.features.gate', true);

        expect(Gate::forUser($this->user)->allows('feature:tax_feature'))->toBeFalse();

        $this->user->enableFeature('tax_feature');

        expect(Gate::forUser($this->user)->allows('feature:tax_feature'))->toBeTrue()
            ->and(Gate::forUser($this->user)->allows('some-other-ability'))->toBeFalse();
    });
});

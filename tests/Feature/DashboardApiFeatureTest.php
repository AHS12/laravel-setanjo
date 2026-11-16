<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Setanjo;
use Ahs12\Setanjo\Tests\Models\Branch;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    Setanjo::auth(fn () => true);
});

afterEach(function () {
    Setanjo::flush();
});

function settingRow($response, string $key): ?array
{
    return collect($response->json('data'))->firstWhere('key', $key);
}

describe('Dashboard settings API', function () {
    it('lists defined and stored settings with effective meta, without feature flags', function () {
        Settings::set('legacy_key', 'kept');
        $audit = Settings::groups()->create(['name' => 'Audit']);

        $response = $this->getJson('/setanjo/api/settings')->assertOk();
        $keys = collect($response->json('data'))->pluck('key');

        expect($keys)->toContain('company_name', 'retention_days', 'legacy_key')
            ->not->toContain('tax_feature', 'beta_feature');

        $retention = settingRow($response, 'retention_days');
        expect($retention)
            ->toMatchArray(['type' => 'integer', 'value' => 180, 'source' => 'default', 'stored' => false, 'group_id' => $audit->id])
            ->and($retention['meta'])->toBe(['unit' => 'days', 'min' => 1, 'max' => 3650]);

        expect(settingRow($response, 'legacy_key'))->toMatchArray(['defined' => false, 'stored' => true, 'value' => 'kept']);
    });

    it('sets a global value, coerced to the defined type', function () {
        $this->putJson('/setanjo/api/settings/retention_days', ['value' => '90'])
            ->assertOk()
            ->assertJsonPath('data.value', 90)
            ->assertJsonPath('data.source', 'global');

        expect(Settings::get('retention_days'))->toBe(90);
    });

    it('returns 422 with errors for invalid values', function () {
        $this->putJson('/setanjo/api/settings/retention_days', ['value' => 0])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['value']]);

        $this->putJson('/setanjo/api/settings/frequency', ['value' => 'hourly'])->assertUnprocessable();
    });

    it('refuses to set feature flags as settings', function () {
        $this->putJson('/setanjo/api/settings/tax_feature', ['value' => true])->assertUnprocessable();
    });

    it('sets and removes a scoped override', function () {
        $company = Company::query()->create(['name' => 'Initech']);

        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech Ltd', 'scope_type' => 'company', 'scope_id' => $company->id])
            ->assertOk()
            ->assertJsonPath('data.overridden', true)
            ->assertJsonPath('data.effective', 'Initech Ltd')
            ->assertJsonPath('data.source', 'scope')
            ->assertJsonPath('data.source_scope.label', 'Initech');

        expect(Settings::for($company)->get('company_name'))->toBe('Initech Ltd')
            ->and(Settings::get('company_name'))->toBe('Acme');

        $this->deleteJson('/setanjo/api/settings/company_name', ['scope_type' => 'company', 'scope_id' => $company->id])
            ->assertOk()
            ->assertJsonPath('data.overridden', false)
            ->assertJsonPath('data.effective', 'Acme');
    });

    it('rejects overrides for scopes the definition does not allow', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->putJson('/setanjo/api/settings/company_only', ['value' => 'x', 'scope_type' => 'user', 'scope_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['scope']]);
    });

    it('returns 404 for unknown scope types and records', function () {
        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'x', 'scope_type' => 'planet', 'scope_id' => 1])->assertNotFound();
        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'x', 'scope_type' => 'company', 'scope_id' => 999])->assertNotFound();
    });

    it('does not change locked or readonly settings', function () {
        Settings::set('company_name', 'Acme');
        Settings::repository()->updateAttributes('company_name', ['is_locked' => true]);
        Settings::setMeta('phone', ['readonly' => true]);

        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])->assertForbidden();
        $this->deleteJson('/setanjo/api/settings/company_name')->assertForbidden();
        $this->putJson('/setanjo/api/settings/phone', ['value' => '123'])->assertForbidden();

        expect(Settings::get('company_name'))->toBe('Acme');
    });

    it('masks sensitive values', function () {
        Settings::set('api_secret', 'hunter2', meta: ['sensitive' => true]);

        $row = settingRow($this->getJson('/setanjo/api/settings'), 'api_secret');

        expect($row)->toMatchArray(['value' => null, 'masked' => true, 'sensitive' => true]);
        expect(json_encode($row))->not->toContain('hunter2');
    });

    it('replaces or merges stored meta', function () {
        $this->putJson('/setanjo/api/settings/retention_days/meta', ['meta' => ['help' => 'Audit log retention']])
            ->assertOk()
            ->assertJsonPath('data.stored_meta', ['help' => 'Audit log retention'])
            ->assertJsonPath('data.meta.unit', 'days');

        $this->putJson('/setanjo/api/settings/retention_days/meta', ['meta' => ['unit' => 'd'], 'merge' => true])
            ->assertOk()
            ->assertJsonPath('data.stored_meta', ['help' => 'Audit log retention', 'unit' => 'd']);

        $this->putJson('/setanjo/api/settings/retention_days/meta', ['meta' => null])
            ->assertOk()
            ->assertJsonPath('data.stored_meta', null);
    });

    it('moves settings into a group in order', function () {
        $group = Settings::groups()->create(['name' => 'General']);

        $response = $this->postJson('/setanjo/api/settings/order', ['group_id' => $group->id, 'keys' => ['frequency', 'company_name']])->assertOk();

        expect(settingRow($response, 'frequency'))->toMatchArray(['group_id' => $group->id, 'sort_order' => 0])
            ->and(settingRow($response, 'company_name'))->toMatchArray(['group_id' => $group->id, 'sort_order' => 1]);
    });
});

describe('Dashboard feature flags API', function () {
    it('lists defined flags with their global state', function () {
        $response = $this->getJson('/setanjo/api/features')->assertOk();

        expect(settingRow($response, 'beta_feature'))->toMatchArray(['global' => null, 'enabled' => true, 'source' => 'default'])
            ->and(settingRow($response, 'tax_feature'))->toMatchArray(['enabled' => false]);
    });

    it('toggles flags globally, per scope and back to inherit', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->putJson('/setanjo/api/features/tax_feature', ['state' => true])
            ->assertOk()
            ->assertJsonPath('data.global', true)
            ->assertJsonPath('data.enabled', true);

        $this->putJson('/setanjo/api/features/tax_feature', ['state' => false, 'scope_type' => 'user', 'scope_id' => $user->id])
            ->assertOk()
            ->assertJsonPath('data.override', false)
            ->assertJsonPath('data.effective', false)
            ->assertJsonPath('data.source', 'scope');

        expect(Settings::feature('tax_feature')->for($user)->enabled())->toBeFalse()
            ->and(Settings::feature('tax_feature')->enabled())->toBeTrue();

        $this->putJson('/setanjo/api/features/tax_feature', ['state' => null, 'scope_type' => 'user', 'scope_id' => $user->id])
            ->assertOk()
            ->assertJsonPath('data.override', null)
            ->assertJsonPath('data.effective', true);

        expect(settingRow($this->getJson('/setanjo/api/features'), 'tax_feature')['override_count'])->toBe(0);
    });

    it('validates the state', function () {
        $this->putJson('/setanjo/api/features/tax_feature', ['state' => 'maybe'])->assertUnprocessable();
    });

    it('updates flag meta and order', function () {
        $group = Settings::groups()->create(['name' => 'Billing']);

        $this->putJson('/setanjo/api/features/tax_feature/meta', ['meta' => ['owner' => 'finance']])
            ->assertOk()
            ->assertJsonPath('data.meta.owner', 'finance');

        $response = $this->postJson('/setanjo/api/features/order', ['group_id' => $group->id, 'keys' => ['beta_feature', 'tax_feature']])->assertOk();

        expect(settingRow($response, 'tax_feature'))->toMatchArray(['group_id' => $group->id, 'sort_order' => 1]);
    });
});

describe('Dashboard groups API', function () {
    it('creates, updates, reorders and deletes groups', function () {
        $first = $this->postJson('/setanjo/api/groups', ['name' => 'General Settings', 'icon' => 'cog'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'general-settings')
            ->json('data.id');
        $second = $this->postJson('/setanjo/api/groups', ['name' => 'Mail'])->assertCreated()->json('data.id');

        $this->putJson("/setanjo/api/groups/{$first}", ['name' => 'General'])->assertOk()->assertJsonPath('data.name', 'General');

        $this->postJson('/setanjo/api/groups/order', ['ids' => [$second, $first]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second);

        Settings::groups()->assign('company_name', $first);
        $this->deleteJson("/setanjo/api/groups/{$first}")->assertNoContent();

        expect(SettingGroup::query()->find($first))->toBeNull()
            ->and(Setting::global()->where('key', 'company_name')->value('group_id'))->toBeNull();
    });

    it('validates groups', function () {
        $this->postJson('/setanjo/api/groups', [])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson('/setanjo/api/groups', ['name' => 'Mail'])->assertCreated();
        $this->postJson('/setanjo/api/groups', ['name' => 'Mail'])->assertUnprocessable();

        $this->putJson('/setanjo/api/groups/999', ['name' => 'Nope'])->assertNotFound();
    });
});

describe('Dashboard scopes API', function () {
    it('lists scope types from the tenancy config', function () {
        $this->getJson('/setanjo/api/scopes')
            ->assertOk()
            ->assertJsonPath('data.*.alias', ['company', 'user']);
    });

    it('uses configured scopes with labels and search columns', function () {
        config()->set('setanjo.scopes', [
            User::class => ['search' => ['name', 'email'], 'description' => fn (User $user) => $user->email],
        ]);
        User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        User::query()->create(['name' => 'John', 'email' => 'john@example.org']);

        $this->getJson('/setanjo/api/scopes')->assertJsonCount(1, 'data');

        $this->getJson('/setanjo/api/scopes/user?search=example.org')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'John')
            ->assertJsonPath('data.0.description', 'john@example.org');
    });

    it('lists records that have overrides', function () {
        $acme = Company::query()->create(['name' => 'Acme']);
        Company::query()->create(['name' => 'Other']);
        Settings::for($acme)->set('company_name', 'Acme Inc');
        Settings::feature('tax_feature')->for($acme)->enable();

        $this->getJson('/setanjo/api/scopes/company')
            ->assertOk()
            ->assertJsonPath('overridden', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Acme')
            ->assertJsonPath('data.0.settings', 1)
            ->assertJsonPath('data.0.features', 1);
    });

    it('shows every setting and flag as one record sees it', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('company_name', 'Global Co');
        Settings::for($user)->set('frequency', 'weekly');
        Settings::feature('beta_feature')->for($user)->disable();

        $response = $this->getJson("/setanjo/api/scopes/user/{$user->id}")->assertOk();

        expect($response->json('data.scope.label'))->toBe('Jane');

        $settings = collect($response->json('data.settings'))->keyBy('key');
        expect($settings['frequency'])->toMatchArray(['overridden' => true, 'override' => 'weekly', 'source' => 'scope'])
            ->and($settings['company_name'])->toMatchArray(['overridden' => false, 'effective' => 'Global Co', 'source' => 'global'])
            ->and($settings['company_only'])->toMatchArray(['allowed' => false]);

        $features = collect($response->json('data.features'))->keyBy('key');
        expect($features['beta_feature'])->toMatchArray(['override' => false, 'effective' => false, 'source' => 'scope']);
    });
});

describe('Dashboard scope edge cases', function () {
    it('searches and labels models without a name column', function () {
        config()->set('setanjo.scopes', [Branch::class => []]);
        Branch::query()->create(['title' => 'Dhaka Main', 'code' => 'DHK']);
        Branch::query()->create(['title' => 'Chattogram', 'code' => 'CTG']);

        $this->getJson('/setanjo/api/scopes/branch?search=Dhaka')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Dhaka Main');

        $this->getJson('/setanjo/api/scopes/branch?search=CTG')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Chattogram');
    });

    it('finds UUID-keyed records by id and ignores malformed ids', function () {
        config()->set('setanjo.scopes', [Branch::class => []]);
        $branch = Branch::query()->create(['title' => 'Sylhet']);

        $this->getJson('/setanjo/api/scopes/branch?search='.$branch->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branch->id);

        $this->getJson("/setanjo/api/scopes/branch/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.scope.label', 'Sylhet');

        $this->getJson('/setanjo/api/scopes/branch/not-a-uuid')->assertNotFound();
        $this->getJson('/setanjo/api/scopes/company/abc')->assertNotFound();
    });

    it('opens soft-deleted records and says they are trashed', function () {
        config()->set('setanjo.scopes', [Branch::class => []]);
        $branch = Branch::query()->create(['title' => 'Closed branch']);
        $branch->delete();

        $this->getJson('/setanjo/api/scopes/branch?search=Closed')->assertJsonCount(0, 'data');
        $this->getJson("/setanjo/api/scopes/branch/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.scope.trashed', true)
            ->assertJsonPath('data.scope.state', 'found');
    });

    it('explains records hidden by a global scope', function () {
        config()->set('setanjo.scopes', [Branch::class => []]);
        $branch = Branch::query()->create(['title' => 'Inactive', 'is_active' => false]);

        $this->getJson("/setanjo/api/scopes/branch/{$branch->id}")
            ->assertNotFound()
            ->assertJson(['message' => __('setanjo::dashboard.scope_hidden', ['type' => 'Branch', 'id' => $branch->id])]);

    });

    it('shows records hidden by a global scope when the type sets without_scopes', function () {
        config()->set('setanjo.scopes', [Branch::class => ['without_scopes' => true]]);
        $branch = Branch::query()->create(['title' => 'Inactive', 'is_active' => false]);

        $this->getJson("/setanjo/api/scopes/branch/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.scope.label', 'Inactive');
    });

    it('lists, opens and clears overrides of deleted records', function () {
        $acme = Company::query()->create(['name' => 'Acme']);
        Settings::for($acme)->set('company_name', 'Acme Inc');
        Settings::feature('tax_feature')->for($acme)->enable();
        $id = $acme->id;
        $acme->delete();

        $this->getJson('/setanjo/api/scopes/company')
            ->assertOk()
            ->assertJsonPath('data.0.state', 'missing')
            ->assertJsonPath('data.0.label', "Company #{$id}");

        $show = $this->getJson("/setanjo/api/scopes/company/{$id}")->assertOk();
        expect($show->json('data.scope.state'))->toBe('missing')
            ->and(collect($show->json('data.settings'))->pluck('key')->all())->toBe(['company_name'])
            ->and($show->json('data.settings.0'))->toMatchArray(['override' => 'Acme Inc', 'source' => 'missing'])
            ->and(collect($show->json('data.features'))->pluck('key')->all())->toBe(['tax_feature']);

        // New values need a live record; clearing does not.
        $this->putJson('/setanjo/api/settings/company_name', ['value' => 'X', 'scope_type' => 'company', 'scope_id' => $id])->assertNotFound();
        $this->putJson('/setanjo/api/features/tax_feature', ['state' => true, 'scope_type' => 'company', 'scope_id' => $id])->assertNotFound();
        $this->deleteJson('/setanjo/api/settings/company_name', ['scope_type' => 'company', 'scope_id' => $id])->assertOk();

        $this->deleteJson("/setanjo/api/scopes/company/{$id}")->assertOk();

        expect(Settings::featureRepository()->overrideCounts())->toBe([])
            ->and(Setting::query()->whereNotNull('tenantable_type')->count())->toBe(0);
        $this->getJson("/setanjo/api/scopes/company/{$id}")->assertNotFound();
    });

    it('clears every override of a live record', function () {
        $acme = Company::query()->create(['name' => 'Acme']);
        Settings::for($acme)->set('company_name', 'Acme Inc');
        Settings::feature('tax_feature')->for($acme)->enable();

        $this->deleteJson("/setanjo/api/scopes/company/{$acme->id}")->assertOk();

        expect(Settings::for($acme)->has('company_name'))->toBeFalse()
            ->and(Settings::featureRepository()->overrides($acme))->toBe([]);
    });

    it('pages records with overrides in id order', function () {
        $companies = collect(range(1, 3))->map(fn ($i) => Company::query()->create(['name' => "Company {$i}"]));
        $companies->reverse()->each(fn (Company $company) => Settings::for($company)->set('company_name', $company->name));

        $this->getJson('/setanjo/api/scopes/company?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.*.label', ['Company 1', 'Company 2']);

        $this->getJson('/setanjo/api/scopes/company?per_page=2&page=2')
            ->assertJsonPath('data.*.label', ['Company 3']);
    });

    it('reports overrides of deleted records and unknown scope types in drift', function () {
        $acme = Company::query()->create(['name' => 'Acme']);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::for($acme)->set('company_name', 'Acme Inc');
        Settings::for($user)->set('frequency', 'weekly');
        $acme->delete();
        config()->set('setanjo.scopes', [Company::class]);

        $this->getJson('/setanjo/api/drift')
            ->assertOk()
            ->assertJsonPath('data.missing_records.0.label', "Company #{$acme->id}")
            ->assertJsonPath('data.unknown_scope_types.0.class', User::class)
            ->assertJsonPath('data.unknown_scope_types.0.settings', 1);
    });
});

describe('Dashboard drift and sync API', function () {
    it('reports drift and previews sync before writing', function () {
        Settings::set('stale_key', 'x');

        $this->getJson('/setanjo/api/drift')
            ->assertOk()
            ->assertJsonPath('data.stale', ['stale_key'])
            ->assertJsonFragment(['key' => 'company_name', 'label' => 'Company Name']);

        $preview = $this->postJson('/setanjo/api/sync', ['dry_run' => true])->assertOk();
        expect($preview->json('output'))->toContain('DRY RUN')
            ->and(Setting::global()->where('key', 'company_name')->exists())->toBeFalse();

        $this->postJson('/setanjo/api/sync', ['prune' => true])
            ->assertOk()
            ->assertJsonPath('exit_code', 0)
            ->assertJsonPath('data.missing', [])
            ->assertJsonPath('data.stale', []);

        expect(Setting::global()->where('key', 'company_name')->exists())->toBeTrue();
    });
});

describe('Dashboard history API', function () {
    it('says when history is disabled', function () {
        $this->getJson('/setanjo/api/history')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('data', []);
    });

    it('lists history entries with filters', function () {
        config()->set('setanjo.history.enabled', true);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $this->actingAs($user)->putJson('/setanjo/api/settings/company_name', ['value' => 'Initech'])->assertOk();
        $this->actingAs($user)->putJson('/setanjo/api/features/tax_feature', ['state' => true])->assertOk();

        expect(SettingHistory::query()->count())->toBe(2);

        $this->getJson('/setanjo/api/history?key=company_name')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'set')
            ->assertJsonPath('data.0.new_value', 'Initech')
            ->assertJsonPath('data.0.causer.label', 'Jane');

        $this->getJson('/setanjo/api/history?action=toggle')->assertJsonCount(1, 'data');
        $this->getJson('/setanjo/api/history?action=nope')->assertUnprocessable();
    });
});

describe('Dashboard import and export API', function () {
    it('exports a downloadable JSON file', function () {
        Settings::set('company_name', 'Initech');

        $response = $this->get('/setanjo/api/export')->assertOk();

        expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="setanjo-')
            ->and(collect(json_decode($response->getContent(), true)['settings'])->pluck('key'))->toContain('company_name');
    });

    it('imports an uploaded export, with a dry run first', function () {
        Settings::set('company_name', 'Initech');
        $export = $this->get('/setanjo/api/export')->getContent();
        Setting::query()->delete();
        Settings::flushCache();

        $file = UploadedFile::fake()->createWithContent('settings.json', $export);

        $this->post('/setanjo/api/import', ['file' => $file, 'dry_run' => true], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('exit_code', 0);
        expect(Setting::query()->count())->toBe(0);

        $file = UploadedFile::fake()->createWithContent('settings.json', $export);
        $this->post('/setanjo/api/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        expect(Settings::get('company_name'))->toBe('Initech');
    });

    it('rejects files that are not setanjo exports', function () {
        $file = UploadedFile::fake()->createWithContent('settings.json', '{"hello":"world"}');

        $this->post('/setanjo/api/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('setanjo::dashboard.invalid_import'));

        $file = UploadedFile::fake()->createWithContent('settings.json', 'not json');
        $this->post('/setanjo/api/import', ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
    });
});

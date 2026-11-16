<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Setanjo;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    Setanjo::auth(fn () => true);
});

afterEach(function () {
    Setanjo::flush();
});

describe('Dashboard overview', function () {
    it('counts settings, flags and overrides per scope type', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $company = Company::query()->create(['name' => 'Initech']);
        Settings::set('company_name', 'Global Co');
        Settings::for($user)->set('frequency', 'weekly');
        Settings::for($company)->set('frequency', 'daily');
        Settings::feature('tax_feature')->enable();
        Settings::feature('beta_feature')->for($user)->disable();

        $data = $this->getJson('/setanjo/api/overview')->assertOk()->json('data');

        expect($data['features'])->toMatchArray(['total' => 2, 'on' => 2, 'off' => 0, 'overridden' => 1])
            ->and($data['settings']['stored'])->toBe(1)
            ->and($data['settings']['overridden'])->toBe(1)
            ->and(collect($data['scopes'])->keyBy('type')['user'])->toMatchArray(['records' => 1, 'settings' => 1, 'features' => 1])
            ->and($data['most_overridden'][0])->toMatchArray(['kind' => 'setting', 'key' => 'frequency', 'overrides' => 2]);
    });
});

describe('Dashboard per-key overrides', function () {
    it('lists the records that override a setting, newest first, by scope type', function () {
        $jane = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $john = User::query()->create(['name' => 'John', 'email' => 'john@example.com']);
        $company = Company::query()->create(['name' => 'Initech']);
        Settings::for($jane)->set('frequency', 'weekly');
        Settings::for($john)->set('frequency', 'daily');
        Settings::for($company)->set('frequency', 'weekly');

        $response = $this->getJson('/setanjo/api/settings/frequency/overrides')->assertOk();

        expect($response->json('meta.total'))->toBe(3)
            ->and(collect($response->json('data'))->pluck('scope.label')->all())->toBe(['Initech', 'John', 'Jane'])
            ->and($response->json('data.1.value'))->toBe('daily');

        $this->getJson('/setanjo/api/settings/frequency/overrides?scope_type=company')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scope.type', 'company');

        $this->getJson('/setanjo/api/settings/nope/overrides')->assertNotFound();
        $this->getJson('/setanjo/api/settings/frequency/overrides?scope_type=planet')->assertNotFound();
    });

    it('masks overridden values of sensitive settings', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('api_secret', 'global', meta: ['sensitive' => true]);
        Settings::for($user)->set('api_secret', 'hunter2');

        $response = $this->getJson('/setanjo/api/settings/api_secret/overrides')->assertOk();

        expect($response->json('data.0.value'))->toBeNull()
            ->and($response->getContent())->not->toContain('hunter2');
    });

    it('lists the records that override a flag', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::feature('tax_feature')->for($user)->enable();

        $this->getJson('/setanjo/api/features/tax_feature/overrides')
            ->assertOk()
            ->assertJsonPath('data.0.scope.label', 'Jane')
            ->assertJsonPath('data.0.value', true)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/setanjo/api/features/company_name/overrides')->assertNotFound();
    });

    it('shows the full resolution chain of a record', function () {
        $company = Company::query()->create(['name' => 'Initech']);
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::resolveScopesUsing(fn ($subject) => $subject instanceof User ? [$subject, $company] : [$subject]);
        Settings::set('frequency', 'weekly');
        Settings::for($company)->set('frequency', 'daily');
        Settings::feature('tax_feature')->enable();

        $response = $this->getJson("/setanjo/api/scopes/user/{$user->id}")->assertOk();
        $chain = collect($response->json('data.settings'))->firstWhere('key', 'frequency')['chain'];

        expect(collect($chain)->map(fn ($level) => [$level['level'], $level['scope']['label'] ?? null, $level['set'], $level['value']])->all())->toBe([
            ['scope', 'Jane', false, null],
            ['scope', 'Initech', true, 'daily'],
            ['global', null, true, 'weekly'],
            ['default', null, true, 'daily'],
        ]);

        $flag = collect($response->json('data.features'))->firstWhere('key', 'tax_feature')['chain'];
        expect(collect($flag)->pluck('set')->all())->toBe([false, false, true, true])
            ->and($flag[2]['value'])->toBeTrue();
    });
});

describe('Dashboard bulk flag overrides', function () {
    it('previews, applies and clears a flag for many records by id', function () {
        $users = collect(['Ann', 'Bob', 'Cat'])->map(
            fn ($name) => User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.com']),
        );
        Settings::feature('tax_feature')->for($users[2])->enable();
        $ids = [...$users->map(fn (User $user) => (string) $user->id)->all(), '999', 'abc', ' '];
        $body = fn (?bool $state, bool $dryRun = false) => ['scope_type' => 'user', 'ids' => $ids, 'state' => $state, 'dry_run' => $dryRun];

        $preview = $this->postJson('/setanjo/api/features/tax_feature/overrides', $body(true, true))->assertOk()->json('data');

        expect($preview)->toMatchArray([
            'dry_run' => true, 'matched' => 3, 'changed' => 2, 'unchanged' => 1, 'missing' => ['999', 'abc'], 'not_allowed' => [],
        ])
            ->and(collect($preview['records'])->pluck('label')->all())->toBe(['Ann', 'Bob'])
            ->and(Settings::feature('tax_feature')->for($users[0])->enabled())->toBeFalse();

        $this->postJson('/setanjo/api/features/tax_feature/overrides', $body(true))
            ->assertOk()
            ->assertJsonPath('data.changed', 2)
            ->assertJsonPath('data.feature.override_count', 3);

        expect(Settings::feature('tax_feature')->for($users[0])->enabled())->toBeTrue();

        $this->postJson('/setanjo/api/features/tax_feature/overrides', $body(null))
            ->assertOk()
            ->assertJsonPath('data.changed', 3)
            ->assertJsonPath('data.feature.override_count', 0);
    });

    it('validates bulk requests', function () {
        $url = '/setanjo/api/features/tax_feature/overrides';

        $this->postJson($url, ['scope_type' => 'user', 'ids' => [], 'state' => true])->assertUnprocessable();
        $this->postJson($url, ['scope_type' => 'user', 'ids' => ['1'], 'state' => 'yes'])->assertUnprocessable();
        $this->postJson($url, ['scope_type' => 'planet', 'ids' => ['1'], 'state' => true])->assertNotFound();
        $this->postJson('/setanjo/api/features/nope/overrides', ['scope_type' => 'user', 'ids' => ['1'], 'state' => true])->assertNotFound();
    });

    it('is blocked in readonly mode, even as a dry run', function () {
        config()->set('setanjo.dashboard.readonly', true);

        $this->postJson('/setanjo/api/features/tax_feature/overrides', ['scope_type' => 'user', 'ids' => ['1'], 'state' => true, 'dry_run' => true])
            ->assertForbidden();
    });
});

describe('Dashboard flag rules', function () {
    it('changes only the rules sent', function () {
        $this->putJson('/setanjo/api/features/beta_feature/rules', ['rollout' => 30, 'requires' => ['tax_feature']])
            ->assertOk()
            ->assertJsonPath('data.rules.rollout', 30)
            ->assertJsonPath('data.rules.requires', ['tax_feature']);

        $this->putJson('/setanjo/api/features/beta_feature/rules', ['enabled_from' => '2030-01-01T09:00:00Z'])
            ->assertOk()
            ->assertJsonPath('data.rules.rollout', 30)
            ->assertJsonPath('data.window', 'pending');

        $this->putJson('/setanjo/api/features/beta_feature/rules', ['rollout' => null, 'requires' => null, 'enabled_from' => null])
            ->assertOk()
            ->assertJsonPath('data.rules', ['enabled_from' => null, 'enabled_until' => null, 'rollout' => null, 'requires' => [], 'archived_at' => null])
            ->assertJsonPath('data.window', null);
    });

    it('validates rules', function (array $body, string $field) {
        $this->putJson('/setanjo/api/features/beta_feature/rules', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'rollout over 100' => [['rollout' => 101], 'rollout'],
        'not a date' => [['enabled_from' => 'soon'], 'enabled_from'],
        'end before start' => [['enabled_from' => '2030-01-02', 'enabled_until' => '2030-01-01'], 'enabled_until'],
        'unknown flag' => [['requires' => ['nope']], 'requires'],
        'itself' => [['requires' => ['beta_feature']], 'requires'],
    ]);

    it('explains which rule decided a record\'s state', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::feature('beta_feature')->requires('tax_feature');

        $this->getJson("/setanjo/api/features/beta_feature?scope_type=user&scope_id={$user->id}")
            ->assertOk()
            ->assertJsonPath('data.effective', false)
            ->assertJsonPath('data.source', 'dependency')
            ->assertJsonPath('data.detail', 'tax_feature');
    });

    it('archives and restores a flag', function () {
        $this->postJson('/setanjo/api/features/beta_feature/archive')->assertOk()->assertJsonPath('data.archived', true);

        expect(collect($this->getJson('/setanjo/api/features')->json('data'))->pluck('key'))->not->toContain('beta_feature')
            ->and(collect($this->getJson('/setanjo/api/features?archived=1')->json('data'))->pluck('key')->all())->toBe(['beta_feature'])
            ->and($this->getJson('/setanjo/api/overview')->json('data.features.archived'))->toBe(1);

        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $this->getJson("/setanjo/api/features/beta_feature?scope_type=user&scope_id={$user->id}")
            ->assertOk()
            ->assertJsonPath('data.effective', false)
            ->assertJsonPath('data.source', 'archived');

        $this->postJson('/setanjo/api/features/beta_feature/restore')->assertOk()->assertJsonPath('data.archived', false);
        $this->postJson('/setanjo/api/features/nope/archive')->assertNotFound();
    });

    it('is blocked in readonly mode', function () {
        config()->set('setanjo.dashboard.readonly', true);

        $this->putJson('/setanjo/api/features/beta_feature/rules', ['rollout' => 10])->assertForbidden();
        $this->postJson('/setanjo/api/features/beta_feature/archive')->assertForbidden();
    });

    it('reverts a rule change from history', function () {
        config()->set('setanjo.history.enabled', true);
        Settings::feature('beta_feature')->rollout(20);
        Settings::feature('beta_feature')->rollout(80);
        $id = SettingHistory::query()->orderByDesc('id')->value('id');

        $this->getJson('/setanjo/api/history')->assertJsonPath('data.0.kind', 'feature')->assertJsonPath('data.0.revertable', true);
        $this->postJson("/setanjo/api/history/{$id}/revert")->assertOk()->assertJsonPath('data.kind', 'feature');

        expect(Settings::feature('beta_feature')->rules()->rollout)->toBe(20);
    });
});

describe('Dashboard history revert', function () {
    beforeEach(function () {
        config()->set('setanjo.history.enabled', true);
    });

    it('does not record saves that change nothing', function () {
        Settings::set('company_name', 'Initech');
        Settings::set('company_name', 'Initech');
        Settings::setMeta('company_name', ['owner' => 'ops']);
        Settings::setMeta('company_name', ['owner' => 'ops']);
        Settings::feature('tax_feature')->enable();
        Settings::feature('tax_feature')->enable();

        expect(SettingHistory::query()->orderBy('id')->pluck('action')->all())->toBe(['set', 'meta', 'toggle']);
    });

    it('records the previous flag state', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::feature('tax_feature')->for($user)->enable();
        Settings::feature('tax_feature')->for($user)->disable();
        Settings::feature('tax_feature')->for($user)->inherit();

        $states = SettingHistory::query()->orderBy('id')->get()->map(fn (SettingHistory $entry) => [$entry->old_value, $entry->new_value]);

        expect($states->all())->toBe([['inherit', 'true'], ['true', 'false'], ['false', null]]);
    });

    it('does not revert toggles recorded without the previous state', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::feature('tax_feature')->for($user)->enable();
        $entry = SettingHistory::query()->firstOrFail();
        $entry->forceFill(['old_value' => null])->save(); // as written before 3.1

        $this->getJson('/setanjo/api/history')->assertJsonPath('data.0.revertable', false);
        $this->postJson("/setanjo/api/history/{$entry->id}/revert")
            ->assertUnprocessable()
            ->assertJson(['message' => __('setanjo::dashboard.revert_unknown_state')]);
    });

    it('reverts a value change, a removal, a meta change and a toggle', function () {
        $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        Settings::set('retention_days', 90);
        Settings::set('retention_days', 30);
        Settings::for($user)->set('frequency', 'weekly');
        Settings::for($user)->forget('frequency');
        Settings::setMeta('company_name', ['owner' => 'ops']);
        Settings::feature('tax_feature')->for($user)->enable();

        $entry = fn (string $key, string $action) => SettingHistory::query()
            ->where('setting_key', $key)->where('action', $action)->orderByDesc('id')->firstOrFail()->id;

        $this->postJson('/setanjo/api/history/'.$entry('retention_days', 'set').'/revert')->assertOk()->assertJsonPath('data.kind', 'setting');
        expect(Settings::get('retention_days'))->toBe(90);

        $this->postJson('/setanjo/api/history/'.$entry('frequency', 'forget').'/revert')->assertOk()->assertJsonPath('data.scope.label', 'Jane');
        expect(Settings::for($user)->get('frequency'))->toBe('weekly');

        $this->postJson('/setanjo/api/history/'.$entry('company_name', 'meta').'/revert')->assertOk();
        expect(Settings::meta('company_name', 'owner'))->toBeNull();

        $this->postJson('/setanjo/api/history/'.$entry('tax_feature', 'toggle').'/revert')->assertOk()->assertJsonPath('data.kind', 'feature');
        expect(Settings::feature('tax_feature')->for($user)->override())->toBeNull();
    });

    it('removes the value when the reverted change created it', function () {
        Settings::set('company_name', 'Initech');

        $this->postJson('/setanjo/api/history/'.SettingHistory::query()->value('id').'/revert')->assertOk();

        expect(Settings::has('company_name'))->toBeFalse();
    });

    it('refuses to revert sensitive values and unknown entries', function () {
        Settings::set('api_secret', 'one', meta: ['sensitive' => true]);
        Settings::set('api_secret', 'two');
        $id = SettingHistory::query()->where('action', 'set')->orderByDesc('id')->value('id');

        $this->getJson('/setanjo/api/history?key=api_secret')->assertJsonPath('data.0.revertable', false);
        $this->postJson("/setanjo/api/history/{$id}/revert")
            ->assertUnprocessable()
            ->assertJson(['message' => __('setanjo::dashboard.revert_masked')]);
        $this->postJson('/setanjo/api/history/999999/revert')->assertNotFound();
    });
});

<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Listeners\RecordSettingHistory;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
});

describe('History', function () {
    it('records nothing by default', function () {
        Settings::set('company_name', 'Globex');

        expect(SettingHistory::count())->toBe(0);
    });

    it('records value, forget and meta changes with scope and causer', function () {
        config()->set('setanjo.history.enabled', true);
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $this->actingAs($user);

        Settings::set('retention_days', 90);
        Settings::set('retention_days', 60);
        Settings::for($user)->set('retention_days', 30);
        Settings::for($user)->forget('retention_days');
        Settings::setMeta('retention_days', ['help' => 'x']);

        $rows = SettingHistory::query()->orderBy('id')->get();

        expect($rows)->toHaveCount(5)
            ->and($rows[1]->only(['action', 'old_value', 'new_value']))->toBe(['action' => 'set', 'old_value' => '90', 'new_value' => '60'])
            ->and($rows[2]->scope_type)->toBe(User::class)
            ->and($rows[2]->scope_id)->toBe($user->id)
            ->and($rows[3]->action)->toBe('forget')
            ->and($rows[4]->action)->toBe('meta')
            ->and($rows[4]->new_meta)->toBe(['help' => 'x'])
            ->and($rows[0]->causer_id)->toBe($user->id);
    });

    it('masks sensitive values', function () {
        config()->set('setanjo.history.enabled', true);
        Settings::define('api_secret', ['meta' => ['sensitive' => true]]);

        Settings::set('api_secret', 'abc');
        Settings::set('api_secret', 'def');

        expect(SettingHistory::query()->latest('id')->first()->only(['old_value', 'new_value']))
            ->toBe(['old_value' => RecordSettingHistory::MASK, 'new_value' => RecordSettingHistory::MASK]);
    });
});

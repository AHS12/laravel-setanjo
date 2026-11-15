<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Tests\Models\User;
use Ahs12\Setanjo\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function dropSetanjoTables(): void
{
    Schema::disableForeignKeyConstraints();
    foreach (['setanjo_feature_overrides', 'setanjo_features', 'setanjo_history', 'settings', 'setanjo_groups', 'settings_group'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();
}

describe('Upgrading from v2', function () {
    it('keeps existing v2 data intact', function () {
        dropSetanjoTables();
        [$create, $groups, $columns, $history, $features] = TestCase::migrationStubs();

        (include $create)->up();

        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
        DB::table('settings')->insert([
            ['key' => 'site', 'value' => 'Acme', 'type' => 'string', 'tenantable_type' => null, 'tenantable_id' => null],
            ['key' => 'limit', 'value' => '10', 'type' => 'integer', 'tenantable_type' => null, 'tenantable_id' => null],
            ['key' => 'tags', 'value' => '["a","b"]', 'type' => 'array', 'tenantable_type' => User::class, 'tenantable_id' => $user->id],
        ]);

        (include $groups)->up();
        (include $columns)->up();
        (include $history)->up();
        (include $features)->up();

        expect(Schema::hasTable('setanjo_features'))->toBeTrue()
            ->and(Schema::hasTable('setanjo_feature_overrides'))->toBeTrue()
            ->and(Schema::hasColumns('settings', ['group_id', 'sort_order', 'meta', 'is_locked']))->toBeTrue()
            ->and(Schema::hasTable('setanjo_groups'))->toBeTrue()
            ->and(Schema::hasTable('setanjo_history'))->toBeTrue()
            ->and(Settings::get('site'))->toBe('Acme')
            ->and(Settings::get('limit'))->toBe(10)
            ->and(Settings::for($user)->get('tags'))->toBe(['a', 'b'])
            ->and(Setting::global()->where('key', 'site')->first()->is_locked)->toBeFalse();

        Settings::groups()->create(['name' => 'General']);
        Settings::groups()->assign('site', 'general');
        Settings::setMeta('site', ['help' => 'Name']);

        expect(Settings::meta('site', 'help'))->toBe('Name');
    });

    it('reuses an existing groups table and group_id column (MEM layout)', function () {
        dropSetanjoTables();
        config()->set('setanjo.groups_table', 'settings_group');
        [$create, $groups, $columns, $history, $features] = TestCase::migrationStubs();

        // MEM: v2 settings table + its own settings_group table + group_id column
        (include $create)->up();
        Schema::create('settings_group', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained('settings_group')->nullOnDelete();
        });

        $groupId = DB::table('settings_group')->insertGetId(['name' => 'Finance', 'slug' => 'finance', 'sort_order' => 2]);
        DB::table('settings')->insert(['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean', 'group_id' => $groupId]);

        (include $groups)->up();
        (include $columns)->up();
        (include $history)->up();
        (include $features)->up();

        expect(Schema::hasColumns('settings_group', ['icon', 'meta', 'created_by']))->toBeTrue()
            ->and(Settings::get('tax_enabled'))->toBeTrue()
            ->and(Settings::groups()->keys('finance'))->toBe(['tax_enabled'])
            ->and(Settings::groups()->find('finance')->sort_order)->toBe(2);

        // Rolling back must not drop the app's own group_id column or its data.
        (include $columns)->down();

        expect(Schema::hasColumn('settings', 'group_id'))->toBeTrue()
            ->and(Schema::hasColumn('settings', 'meta'))->toBeFalse()
            ->and(DB::table('settings')->where('key', 'tax_enabled')->value('group_id'))->toBe($groupId);
    });

    it('can roll the v3 columns back', function () {
        [, , $columns] = TestCase::migrationStubs();

        (include $columns)->down();

        expect(Schema::hasColumn('settings', 'meta'))->toBeFalse()
            ->and(Schema::hasColumn('settings', 'group_id'))->toBeFalse();
    });
});

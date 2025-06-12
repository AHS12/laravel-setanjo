<?php

namespace Ahs12\Setanjo\Tests;

use Ahs12\Setanjo\SetanjoServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Ahs12\\Setanjo\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Create the settings table manually since migration might not be working
        $this->createSettingsTable();
        $this->createTestTables();
    }

    protected function getPackageProviders($app)
    {
        return [
            SetanjoServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure package for testing
        config()->set('setanjo.tenancy_mode', 'polymorphic');
        config()->set('setanjo.allowed_tenant_models', [
            \Ahs12\Setanjo\Tests\Models\Company::class,
            \Ahs12\Setanjo\Tests\Models\User::class,
        ]);
        config()->set('setanjo.cache.enabled', false);
        config()->set('setanjo.table', 'settings');
    }

    protected function createSettingsTable(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->longText('value')->nullable();
                $table->string('type')->default('string');
                $table->text('description')->nullable();
                $table->nullableMorphs('tenantable');
                $table->timestamps();

                // $table->unique(['key', 'tenantable_type', 'tenantable_id']);
                // $table->index(['tenantable_type', 'tenantable_id']);
            });
        }
    }

    protected function createTestTables(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamps();
            });
        }
    }
}

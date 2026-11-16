<?php

namespace Ahs12\Setanjo\Tests;

use Ahs12\Setanjo\SetanjoServiceProvider;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
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
        // The dashboard runs behind the web middleware (encrypted cookies, sessions).
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', static::databaseConnection());

        // Configure package for testing
        config()->set('setanjo.tenancy_mode', 'polymorphic');
        config()->set('setanjo.allowed_tenant_models', [
            Company::class,
            User::class,
        ]);
        config()->set('setanjo.cache.enabled', false);
        config()->set('setanjo.table', 'settings');
    }

    /**
     * In-memory SQLite by default. Set DB_DRIVER=mysql|pgsql (plus DB_HOST, DB_PORT,
     * DB_DATABASE, DB_USERNAME, DB_PASSWORD) to run the suite against a real server.
     * A real server is shared between tests, so run it without --parallel.
     *
     * @return array<string, mixed>
     */
    protected static function databaseConnection(): array
    {
        $driver = getenv('DB_DRIVER') ?: 'sqlite';

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => $driver,
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306'),
            'database' => getenv('DB_DATABASE') ?: 'setanjo_test',
            'username' => getenv('DB_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'collation' => $driver === 'pgsql' ? null : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'search_path' => 'public',
        ];
    }

    /**
     * Run the package's real migration stubs, in publish order, so tests cover the
     * v2 create migration plus the v3 upgrade migrations.
     */
    protected function createSettingsTable(): void
    {
        if (config('database.connections.testing.driver') !== 'sqlite') {
            // Real servers keep tables between tests: start every test from an empty schema.
            Schema::dropAllTables();
        }

        if (Schema::hasTable('settings')) {
            return;
        }

        foreach (static::migrationStubs() as $stub) {
            (include $stub)->up();
        }
    }

    /**
     * @return array<int, string>
     */
    public static function migrationStubs(): array
    {
        $dir = __DIR__.'/../database/migrations/';

        return [
            $dir.'create_setanjo_settings_table.php.stub',
            $dir.'create_setanjo_groups_table.php.stub',
            $dir.'add_v3_columns_to_setanjo_settings_table.php.stub',
            $dir.'create_setanjo_history_table.php.stub',
            $dir.'create_setanjo_features_tables.php.stub',
            $dir.'add_v3_2_columns_to_setanjo_features_table.php.stub',
        ];
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

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
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

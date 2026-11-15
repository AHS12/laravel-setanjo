<?php

namespace Ahs12\Setanjo;

use Ahs12\Setanjo\Commands\ClearCacheCommand;
use Ahs12\Setanjo\Commands\ExportCommand;
use Ahs12\Setanjo\Commands\ImportCommand;
use Ahs12\Setanjo\Commands\InstallDefaultsCommand;
use Ahs12\Setanjo\Commands\MigrateFeatureFlagsCommand;
use Ahs12\Setanjo\Commands\SyncCommand;
use Ahs12\Setanjo\Contracts\FeatureRepositoryInterface;
use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Http\Middleware\EnsureFeatureEnabled;
use Ahs12\Setanjo\Listeners\RecordSettingHistory;
use Ahs12\Setanjo\Repositories\DatabaseFeatureRepository;
use Ahs12\Setanjo\Repositories\DatabaseSettingsRepository;
use Ahs12\Setanjo\Support\ScopeResolver;
use Ahs12\Setanjo\Support\SettingsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SetanjoServiceProvider extends PackageServiceProvider
{
    /**
     * Events after which in-memory settings are dropped so long-running workers see fresh values.
     *
     * @var array<int, string>
     */
    protected array $resetEvents = [
        JobProcessing::class,
        'Laravel\Octane\Events\RequestReceived',
        'Laravel\Octane\Events\TaskReceived',
        'Laravel\Octane\Events\TickReceived',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('setanjo')
            ->hasConfigFile('setanjo')
            ->hasTranslations()
            ->hasMigrations([
                'create_setanjo_settings_table',
                'create_setanjo_groups_table',
                'add_v3_columns_to_setanjo_settings_table',
                'create_setanjo_history_table',
                'create_setanjo_features_tables',
            ])
            ->hasCommands([
                ClearCacheCommand::class,
                InstallDefaultsCommand::class,
                SyncCommand::class,
                ExportCommand::class,
                ImportCommand::class,
                MigrateFeatureFlagsCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DefinitionRegistry::class);
        $this->app->singleton(ScopeResolver::class);
        $this->app->singleton(SettingsCache::class);

        $this->app->singleton(SettingsRepositoryInterface::class, function ($app) {
            $driver = config('setanjo.repository', 'database');

            return match ($driver) {
                'database' => new DatabaseSettingsRepository($app->make(SettingsCache::class)),
                default => throw new InvalidArgumentException("Unsupported repository driver: {$driver}"),
            };
        });

        $this->app->singleton(FeatureRepositoryInterface::class, function ($app) {
            return new DatabaseFeatureRepository($app->make(SettingsCache::class));
        });

        $this->app->singleton('setanjo', function ($app) {
            return new SetanjoManager(
                $app->make(SettingsRepositoryInterface::class),
                $app->make(DefinitionRegistry::class),
                $app->make(ScopeResolver::class),
                $app->make(FeatureRepositoryInterface::class),
            );
        });

        $this->app->alias('setanjo', SetanjoManager::class);
    }

    public function packageBooted(): void
    {
        $this->registerMiddleware();
        $this->registerBladeDirectives();
        $this->registerGate();
        $this->registerHistory();
        $this->registerStateReset();
    }

    protected function registerMiddleware(): void
    {
        $alias = config('setanjo.features.middleware_alias', 'setanjo.feature');

        if ($alias) {
            $this->app->make(Router::class)->aliasMiddleware($alias, EnsureFeatureEnabled::class);
        }
    }

    protected function registerBladeDirectives(): void
    {
        // @feature('key') ... @else ... @endfeature
        Blade::if('feature', function (string $key, ?Model $subject = null) {
            return app('setanjo')->feature($key)->for($subject)->enabled();
        });

        // @setting('key') or @setting('key', 'fallback') — effective value, escaped
        Blade::directive('setting', function (string $expression) {
            return "<?php echo e(\\Ahs12\\Setanjo\\Support\\Display::value(app('setanjo')->effective({$expression}))); ?>";
        });
    }

    protected function registerGate(): void
    {
        // Gate::allows('feature:tax_features_enabled') — active when setanjo.features.gate is true
        Gate::before(function ($user, string $ability) {
            if (! config('setanjo.features.gate', false) || ! str_starts_with($ability, 'feature:')) {
                return null;
            }

            $subject = $user instanceof Model ? $user : null;

            return app('setanjo')->feature(substr($ability, 8))->for($subject)->enabled();
        });
    }

    protected function registerHistory(): void
    {
        // The listener checks setanjo.history.enabled itself so it can be toggled at runtime.
        Event::subscribe(RecordSettingHistory::class);
    }

    protected function registerStateReset(): void
    {
        Event::listen($this->resetEvents, function () {
            foreach ([SettingsRepositoryInterface::class, FeatureRepositoryInterface::class] as $repository) {
                if ($this->app->resolved($repository)) {
                    $this->app->make($repository)->reset();
                }
            }
        });
    }
}

<?php

namespace Ahs12\Setanjo;

use Ahs12\Setanjo\Commands\ClearCacheCommand;
use Ahs12\Setanjo\Commands\InstallDefaultsCommand;
use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Repositories\DatabaseSettingsRepository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SetanjoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('setanjo')
            ->hasConfigFile('setanjo')
            ->hasMigration('create_setanjo_settings_table')
            ->hasCommands([
                ClearCacheCommand::class,
                InstallDefaultsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register repository binding
        $this->app->bind(SettingsRepositoryInterface::class, function ($app) {
            $driver = config('setanjo.repository', 'database');

            return match ($driver) {
                'database' => new DatabaseSettingsRepository,
                default => throw new \InvalidArgumentException("Unsupported repository driver: {$driver}"),
            };
        });

        // Register manager
        $this->app->singleton('setanjo', function ($app) {
            return new SetanjoManager($app->make(SettingsRepositoryInterface::class));
        });

        // Register alias
        $this->app->alias('setanjo', SetanjoManager::class);
    }
}

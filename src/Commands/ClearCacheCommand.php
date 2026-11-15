<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Support\SettingsCache;
use Illuminate\Console\Command;

class ClearCacheCommand extends Command
{
    protected $signature = 'setanjo:clear-cache
                            {--tenant= : Clear cache for specific tenant (format: App\\Models\\User:ID)}
                            {--all : Clear all setanjo cache}
                            {--debug : Show debug information}';

    protected $description = 'Clear setanjo settings cache';

    public function handle(SettingsCache $cache): int
    {
        if (! $cache->enabled()) {
            $this->info('Cache is disabled. Nothing to clear.');

            return self::SUCCESS;
        }

        if ($this->option('debug')) {
            $this->showDebugInfo($cache);
        }

        if ($tenant = $this->option('tenant')) {
            $tenantKey = $this->normalizeTenantKey($tenant);

            if ($tenantKey === null) {
                $this->error('Invalid tenant format. Use: ModelClass:ID (e.g., App\\Models\\User:1)');
                $this->error('On Windows, try using quotes: --tenant="App\\Models\\User:1"');

                return self::FAILURE;
            }

            $cache->forget($tenantKey);
            $cache->forget('features:'.$tenantKey);
            $this->info("Cleared cache for tenant: {$tenantKey}");
        } elseif ($this->option('all')) {
            $cache->flush();
            $this->info('Cleared all setanjo cache.');
        } else {
            $cache->forget('global');
            $cache->forget('features:flags');
            $this->info('Cleared global setanjo cache.');
        }

        // Drop this process' in-memory copy as well.
        app('setanjo')->repository()->reset();
        app('setanjo')->featureRepository()->reset();

        return self::SUCCESS;
    }

    protected function showDebugInfo(SettingsCache $cache): void
    {
        $this->info('=== Cache Debug Information ===');
        $this->line('Cache Default Store: '.config('cache.default'));
        $this->line('Setanjo Cache Store: '.(config('setanjo.cache.store') ?: 'default'));
        $this->line('Setanjo Cache Prefix: '.config('setanjo.cache.prefix', 'setanjo'));
        $this->line('Cache TTL: '.config('setanjo.cache.ttl', 3600).' seconds');
        $this->line('Cache Version: '.$cache->version());
        $this->line('Example Cache Key: '.$cache->key('global'));
        $this->line('================================');
    }

    /**
     * Accepts "App\Models\User:1" and repairs shells that strip backslashes ("AppModelsUser:1").
     */
    protected function normalizeTenantKey(string $tenantKey): ?string
    {
        if (! str_contains($tenantKey, '\\') && preg_match('/^([A-Z][a-z0-9]*){3,}:\d+$/', $tenantKey)) {
            [$class, $id] = explode(':', $tenantKey, 2);
            $parts = preg_split('/(?=[A-Z])/', $class, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tenantKey = $parts[0].'\\'.$parts[1].'\\'.implode('', array_slice($parts, 2)).':'.$id;

            if ($this->option('debug')) {
                $this->line("Auto-reconstructed: '{$tenantKey}'");
            }
        }

        $tenantKey = str_replace('\\\\', '\\', $tenantKey);

        return preg_match('/^[A-Za-z0-9_\\\\]+:\d+$/', $tenantKey) ? $tenantKey : null;
    }
}

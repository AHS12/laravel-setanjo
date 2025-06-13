<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
    protected $signature = 'setanjo:clear-cache 
                            {--tenant= : Clear cache for specific tenant (format: App\\Models\\User:ID)}
                            {--all : Clear all setanjo cache}
                            {--debug : Show debug information}';

    protected $description = 'Clear setanjo settings cache';

    public function __construct(protected SettingsRepositoryInterface $repository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('setanjo.cache.enabled', true)) {
            $this->info('Cache is disabled. Nothing to clear.');

            return self::SUCCESS;
        }

        $tenant = $this->option('tenant');
        $all = $this->option('all');
        $debug = $this->option('debug');

        if ($debug) {
            $this->showDebugInfo();
        }

        if ($tenant) {
            $tenant = $this->normalizeTenantKey($tenant);
            $this->clearTenantCache($tenant);
        } elseif ($all) {
            $this->clearAllCache();
        } else {
            $this->clearGlobalCache();
        }

        return self::SUCCESS;
    }

    protected function showDebugInfo(): void
    {
        $this->info('=== Cache Debug Information ===');
        $this->line('Cache Default Store: '.config('cache.default'));
        $this->line('Setanjo Cache Store: '.(config('setanjo.cache.store') ?: 'default'));
        $this->line('Setanjo Cache Prefix: '.config('setanjo.cache.prefix', 'setanjo'));
        $this->line('Cache TTL: '.config('setanjo.cache.ttl', 3600).' seconds');

        $testKey = $this->buildCacheKey('global');
        $this->line('Example Cache Key: '.$testKey);
        $this->line('================================');
    }

    protected function normalizeTenantKey(string $tenantKey): string
    {
        if (! str_contains($tenantKey, '\\') && str_contains($tenantKey, 'ModelsUser:')) {
            $tenantKey = preg_replace('/^App([A-Z][a-z]+)+User:/', 'App\\Models\\User:', $tenantKey);
        } elseif (! str_contains($tenantKey, '\\') && preg_match('/^([A-Z][a-z]+)+([A-Z][a-z]+):(\d+)$/', $tenantKey, $matches)) {
            $fullClass = $matches[0];
            $parts = preg_split('/(?=[A-Z])/', $fullClass, -1, PREG_SPLIT_NO_EMPTY);

            if (count($parts) >= 3) {
                $reconstructed = $parts[0].'\\'.$parts[1].'\\'.implode('', array_slice($parts, 2));
                if ($this->option('debug')) {
                    $this->line("Auto-reconstructed: '{$reconstructed}'");
                }
                $tenantKey = $reconstructed;
            }
        }

        $tenantKey = str_replace('\\\\', '\\', $tenantKey);

        if (! preg_match('/^[A-Za-z\\\\]+:\d+$/', $tenantKey)) {
            $this->error('Invalid tenant format. Use: ModelClass:ID (e.g., App\\Models\\User:1)');
            $this->error('On Windows, try using quotes: --tenant="App\\Models\\User:1"');
            exit(1);
        }

        return $tenantKey;
    }

    /**
     * Clear cache for specific tenant using same logic as repository
     */
    protected function clearTenantCache(string $tenantKey): void
    {
        $cacheKey = $this->buildCacheKey($tenantKey);
        $store = Cache::store(config('setanjo.cache.store'));

        if ($this->option('debug')) {
            $hasCache = $store->has($cacheKey);
            $this->line("Cache key '{$cacheKey}' exists: ".($hasCache ? 'YES' : 'NO'));
        }

        // Use same logic as repository
        $success = $this->clearCacheUsingRepositoryLogic($store, $cacheKey);

        if ($this->option('debug')) {
            $this->line('Cache clear result: '.($success ? 'SUCCESS' : 'FAILED'));

            // Verify it's gone
            $stillExists = $store->has($cacheKey);
            $this->line('Cache still exists after clear: '.($stillExists ? 'YES' : 'NO'));
        }

        $this->info("Cleared cache for tenant: {$tenantKey}");
    }

    /**
     * Clear global settings cache
     */
    protected function clearGlobalCache(): void
    {
        $cacheKey = $this->buildCacheKey('global');
        $store = Cache::store(config('setanjo.cache.store'));

        if ($this->option('debug')) {
            $hasCache = $store->has($cacheKey);
            $this->line("Global cache key '{$cacheKey}' exists: ".($hasCache ? 'YES' : 'NO'));
        }

        // Use same logic as repository
        $success = $this->clearCacheUsingRepositoryLogic($store, $cacheKey);

        if ($this->option('debug')) {
            $this->line('Cache clear result: '.($success ? 'SUCCESS' : 'FAILED'));

            // Verify it's gone
            $stillExists = $store->has($cacheKey);
            $this->line('Cache still exists after clear: '.($stillExists ? 'YES' : 'NO'));
        }

        $this->info('Cleared global setanjo cache.');
    }

    /**
     * Clear all setanjo cache
     */
    protected function clearAllCache(): void
    {
        $store = Cache::store(config('setanjo.cache.store'));

        try {
            // If cache supports tags, clear all setanjo cache at once
            if ($this->supportsCacheTags($store)) {
                if ($this->option('debug')) {
                    $this->line('Using cache tags to clear all setanjo cache...');
                }

                $store->tags(['setanjo'])->flush();
                $this->info('Cleared all setanjo cache using tags.');

                return;
            }

            // Fallback: Clear individual cache entries
            $clearedCount = 0;

            // Get all unique tenant combinations from database
            $tenants = \DB::table(config('setanjo.table', 'settings'))
                ->select('tenantable_type', 'tenantable_id')
                ->whereNotNull('tenantable_type')
                ->whereNotNull('tenantable_id')
                ->distinct()
                ->get();

            if ($this->option('debug')) {
                $this->line('Found '.$tenants->count().' unique tenant combinations in database');
            }

            // Clear global cache
            $globalKey = $this->buildCacheKey('global');
            if ($this->option('debug')) {
                $hasGlobalCache = $store->has($globalKey);
                $this->line('Global cache exists: '.($hasGlobalCache ? 'YES' : 'NO'));
            }

            $success = $this->clearCacheUsingRepositoryLogic($store, $globalKey);
            $clearedCount++;

            if ($this->option('debug')) {
                $this->line('Cleared global cache: '.($success ? 'SUCCESS' : 'FAILED'));
            }

            // Clear each tenant's cache
            foreach ($tenants as $tenant) {
                if ($tenant->tenantable_type && $tenant->tenantable_id) {
                    $tenantKey = "{$tenant->tenantable_type}:{$tenant->tenantable_id}";
                    $cacheKey = $this->buildCacheKey($tenantKey);

                    if ($this->option('debug')) {
                        $hasTenantCache = $store->has($cacheKey);
                        $this->line("Tenant cache '{$tenantKey}' exists: ".($hasTenantCache ? 'YES' : 'NO'));
                    }

                    $success = $this->clearCacheUsingRepositoryLogic($store, $cacheKey);
                    $clearedCount++;

                    if ($this->option('debug')) {
                        $this->line("Cleared tenant cache '{$tenantKey}': ".($success ? 'SUCCESS' : 'FAILED'));
                    }
                }
            }

            $this->info("Processed {$clearedCount} setanjo cache keys.");

            // Verification
            if ($this->option('debug')) {
                $this->line('');
                $this->line('=== Verification ===');

                $globalExists = $store->has($globalKey);
                $this->line('Global cache still exists: '.($globalExists ? 'YES' : 'NO'));

                $verifiedCount = 0;
                foreach ($tenants->take(3) as $tenant) {
                    if ($tenant->tenantable_type && $tenant->tenantable_id) {
                        $tenantKey = "{$tenant->tenantable_type}:{$tenant->tenantable_id}";
                        $cacheKey = $this->buildCacheKey($tenantKey);
                        $exists = $store->has($cacheKey);
                        $this->line("Tenant cache '{$tenantKey}' still exists: ".($exists ? 'YES' : 'NO'));
                        if (! $exists) {
                            $verifiedCount++;
                        }
                    }
                }

                $this->line('Verification: '.$verifiedCount.'/'.min(3, $tenants->count()).' tenant caches successfully cleared');
            }

        } catch (\Exception $e) {
            $this->error('Failed to clear cache: '.$e->getMessage());
            if ($this->option('debug')) {
                $this->error('Exception details: '.$e->getTraceAsString());
            }
        }
    }

    /**
     * Clear cache using the same logic as the repository
     */
    protected function clearCacheUsingRepositoryLogic($store, string $cacheKey): bool
    {
        // Use tags if supported (same as repository)
        if ($this->supportsCacheTags($store)) {
            return $store->tags(['setanjo', 'settings'])->forget($cacheKey);
        } else {
            return $store->forget($cacheKey);
        }
    }

    /**
     * Check if cache store supports tags (copied from repository)
     */
    protected function supportsCacheTags($store): bool
    {
        return method_exists($store, 'tags') &&
            in_array($this->getCurrentCacheDriver(), ['redis', 'memcached']);
    }

    /**
     * Get current cache driver name (copied from repository)
     */
    protected function getCurrentCacheDriver(): string
    {
        $storeConfig = config('setanjo.cache.store');

        return $storeConfig ?: config('cache.default');
    }

    /**
     * Build cache key using same logic as repository
     */
    protected function buildCacheKey(string $tenantKey): string
    {
        $prefix = config('setanjo.cache.prefix', 'setanjo');

        return "{$prefix}:settings:{$tenantKey}";
    }
}

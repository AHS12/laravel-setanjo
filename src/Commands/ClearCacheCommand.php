<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
    protected $signature = 'setanjo:clear-cache 
                            {--tenant= : Clear cache for specific tenant (format: App\\Models\\User:ID)}
                            {--all : Clear all setanjo cache}';

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

    /**
     * Normalize tenant key to handle escaped backslashes
     */
    protected function normalizeTenantKey(string $tenantKey): string
    {
        // Try to reconstruct the proper namespace if backslashes are missing
        if (! str_contains($tenantKey, '\\') && str_contains($tenantKey, 'ModelsUser:')) {
            // Handle common Laravel pattern: AppModelsUser:ID -> App\Models\User:ID
            $tenantKey = preg_replace('/^App([A-Z][a-z]+)+User:/', 'App\\Models\\User:', $tenantKey);
        } elseif (! str_contains($tenantKey, '\\') && preg_match('/^([A-Z][a-z]+)+([A-Z][a-z]+):(\d+)$/', $tenantKey, $matches)) {
            // General pattern reconstruction for missing backslashes
            $fullClass = $matches[0];
            $parts = preg_split('/(?=[A-Z])/', $fullClass, -1, PREG_SPLIT_NO_EMPTY);

            if (count($parts) >= 3) {
                // Assume App\Models\ModelName pattern
                $reconstructed = $parts[0].'\\'.$parts[1].'\\'.implode('', array_slice($parts, 2));
                $this->line("Auto-reconstructed: '{$reconstructed}'");
                $tenantKey = $reconstructed;
            }
        }

        // Handle double-escaped backslashes that might occur in command line
        $tenantKey = str_replace('\\\\', '\\', $tenantKey);

        // Validate format: ModelClass:ID
        if (! preg_match('/^[A-Za-z\\\\]+:\d+$/', $tenantKey)) {
            $this->error('Invalid tenant format. Use: ModelClass:ID (e.g., App\\Models\\User:1)');
            $this->error('On Windows, try using quotes: --tenant="App\\Models\\User:1"');
            exit(1);
        }

        return $tenantKey;
    }

    /**
     * Clear cache for specific tenant
     */
    protected function clearTenantCache(string $tenantKey): void
    {
        $cacheKey = $this->buildCacheKey($tenantKey);

        Cache::store(config('setanjo.cache.store'))->forget($cacheKey);

        $this->info("Cleared cache for tenant: {$tenantKey}");
        $this->line("Cache key used: {$cacheKey}");
    }

    /**
     * Clear global settings cache
     */
    protected function clearGlobalCache(): void
    {
        $cacheKey = $this->buildCacheKey('global');

        Cache::store(config('setanjo.cache.store'))->forget($cacheKey);

        $this->info('Cleared global setanjo cache.');
    }

    /**
     * Clear all setanjo cache using Laravel's cache methods
     */
    protected function clearAllCache(): void
    {
        $store = Cache::store(config('setanjo.cache.store'));

        // Option 1: Use pattern matching for Redis
        if ($this->isRedisStore()) {
            $this->clearRedisPatternCache($store);

            return;
        }

        // Option 2: Clear known cache keys (safer approach)
        if ($this->clearKnownCacheKeys($store)) {
            return;
        }

        // Option 3: Fallback - ask to flush entire store (if supported)
        $this->warn('Cache driver does not support selective clearing.');

        if ($this->confirm('Do you want to flush the entire cache store?')) {
            try {
                if (method_exists($store, 'flush')) {
                    $store->flush();
                    $this->info('Entire cache store flushed.');
                } else {
                    $this->error('Cache store does not support flushing. Please clear cache manually or use --tenant option.');
                }
            } catch (\Exception $e) {
                $this->error('Failed to flush cache: '.$e->getMessage());
            }
        } else {
            $this->info('Cache clearing cancelled.');
        }
    }

    /**
     * Clear known cache keys from database
     */
    protected function clearKnownCacheKeys($store): bool
    {
        try {
            // Get all unique tenant combinations from database
            $tenants = \DB::table(config('setanjo.table', 'settings'))
                ->select('tenantable_type', 'tenantable_id')
                ->distinct()
                ->get();

            $clearedCount = 0;

            // Clear global cache
            $globalKey = $this->buildCacheKey('global');
            $store->forget($globalKey);
            $clearedCount++;

            // Clear each tenant's cache
            foreach ($tenants as $tenant) {
                if ($tenant->tenantable_type && $tenant->tenantable_id) {
                    $tenantKey = "{$tenant->tenantable_type}:{$tenant->tenantable_id}";
                    $cacheKey = $this->buildCacheKey($tenantKey);
                    $store->forget($cacheKey);
                    $clearedCount++;
                }
            }

            $this->info("Cleared {$clearedCount} setanjo cache keys.");

            return true;

        } catch (\Exception $e) {
            $this->error('Failed to clear known cache keys: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Build cache key using same logic as repository
     */
    protected function buildCacheKey(string $tenantKey): string
    {
        $prefix = config('setanjo.cache.prefix', 'setanjo');

        return "{$prefix}:settings:{$tenantKey}";
    }

    /**
     * Check if using Redis store
     */
    protected function isRedisStore(): bool
    {
        $defaultStore = config('cache.default');
        $currentStore = config('setanjo.cache.store') ?: $defaultStore;

        return $currentStore === 'redis' ||
            (is_null(config('setanjo.cache.store')) && $defaultStore === 'redis');
    }

    /**
     * Clear Redis cache using pattern matching
     */
    protected function clearRedisPatternCache($store): void
    {
        try {
            // Try different methods to get Redis connection
            $redis = null;

            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
            } elseif (method_exists($store, 'connection')) {
                $redis = $store->connection();
            } else {
                // Fallback to Laravel's Redis facade
                $redis = \Illuminate\Support\Facades\Redis::connection();
            }

            $prefix = config('setanjo.cache.prefix', 'setanjo');
            $pattern = "{$prefix}:settings:*";

            $keys = $redis->keys($pattern);

            if (! empty($keys)) {
                $redis->del($keys);
                $this->info('Cleared '.count($keys).' setanjo cache keys from Redis.');
            } else {
                $this->info('No setanjo cache keys found in Redis.');
            }

        } catch (\Exception $e) {
            $this->error('Failed to clear Redis cache: '.$e->getMessage());
            $this->info('Falling back to known cache keys method...');
            $this->clearKnownCacheKeys($store);
        }
    }
}

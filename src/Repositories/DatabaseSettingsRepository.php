<?php

namespace Ahs12\Setanjo\Repositories;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    protected array $loadedSettings = [];

    public function get(string $key, $default = null, $tenant = null)
    {
        $this->loadSettingsForTenant($tenant);

        $tenantKey = $this->getTenantKey($tenant);

        return $this->loadedSettings[$tenantKey][$key] ?? $default;
    }

    public function set(string $key, $value, $tenant = null): void
    {
        $setting = $this->findOrCreateSetting($key, $tenant);

        $setting->setTypeFromValue($value);
        $setting->value = $value;
        $setting->save();

        // Update loaded settings cache
        $tenantKey = $this->getTenantKey($tenant);
        $this->loadedSettings[$tenantKey][$key] = $value;

        // update cache
        $this->updateCache($tenant);
    }

    public function has(string $key, $tenant = null): bool
    {
        $this->loadSettingsForTenant($tenant);

        $tenantKey = $this->getTenantKey($tenant);

        return isset($this->loadedSettings[$tenantKey][$key]);
    }

    public function forget(string $key, $tenant = null): void
    {
        Setting::forTenant($tenant)->where('key', $key)->delete();

        // Update loaded settings cache
        $tenantKey = $this->getTenantKey($tenant);
        unset($this->loadedSettings[$tenantKey][$key]);

        // update cache
        $this->updateCache($tenant);
    }

    public function all($tenant = null): Collection
    {
        $this->loadSettingsForTenant($tenant);

        $tenantKey = $this->getTenantKey($tenant);

        return collect($this->loadedSettings[$tenantKey] ?? []);
    }

    public function flush($tenant = null): void
    {
        Setting::forTenant($tenant)->delete();

        $tenantKey = $this->getTenantKey($tenant);
        $this->loadedSettings[$tenantKey] = [];

        // Clear cache
        $this->clearCache($tenant);
    }

    protected function loadSettingsForTenant($tenant): void
    {
        $tenantKey = $this->getTenantKey($tenant);

        if (isset($this->loadedSettings[$tenantKey])) {
            return;
        }

        $cacheKey = $this->getCacheKey($tenant);

        if (config('setanjo.cache.enabled', true)) {
            $store = Cache::store(config('setanjo.cache.store'));

            // Use tags if supported (Redis, Memcached)
            if ($this->supportsCacheTags($store)) {
                $settings = $store->tags(['setanjo', 'settings'])
                    ->remember($cacheKey, config('setanjo.cache.ttl', 3600), function () use ($tenant) {
                        return $this->loadSettingsFromDatabase($tenant);
                    });
            } else {
                $settings = $store->remember($cacheKey, config('setanjo.cache.ttl', 3600), function () use ($tenant) {
                    return $this->loadSettingsFromDatabase($tenant);
                });
            }
        } else {
            $settings = $this->loadSettingsFromDatabase($tenant);
        }

        $this->loadedSettings[$tenantKey] = $settings;
    }

    /**
     * Check if cache store supports tags
     */
    protected function supportsCacheTags($store): bool
    {
        return method_exists($store, 'tags') &&
            in_array($this->getCurrentCacheDriver(), ['redis', 'memcached']);
    }

    /**
     * Get current cache driver name
     */
    protected function getCurrentCacheDriver(): string
    {
        $storeConfig = config('setanjo.cache.store');

        return $storeConfig ?: config('cache.default');
    }

    protected function loadSettingsFromDatabase($tenant): array
    {
        $settings = [];

        Setting::forTenant($tenant)->get()->each(function ($setting) use (&$settings) {
            $settings[$setting->key] = $setting->value;
        });

        return $settings;
    }

    protected function findOrCreateSetting(string $key, $tenant = null): Setting
    {
        $query = Setting::forTenant($tenant)->where('key', $key);

        $setting = $query->first();

        if (! $setting) {
            $setting = new Setting(['key' => $key]);

            if ($tenant) {
                $column = config('setanjo.tenantable_column', 'tenantable');
                $setting->{$column.'_type'} = get_class($tenant);
                $setting->{$column.'_id'} = $tenant->getKey();
            }
        }

        return $setting;
    }

    protected function getTenantKey($tenant): string
    {
        if ($tenant === null) {
            return 'global';
        }

        return get_class($tenant).':'.$tenant->getKey();
    }

    protected function getCacheKey($tenant): string
    {
        $prefix = config('setanjo.cache.prefix', 'setanjo');
        $tenantKey = $this->getTenantKey($tenant);

        return "{$prefix}:settings:{$tenantKey}";
    }

    protected function clearCache($tenant): void
    {
        if (! config('setanjo.cache.enabled', true)) {
            return;
        }

        $store = Cache::store(config('setanjo.cache.store'));
        $cacheKey = $this->getCacheKey($tenant);

        // Use tags if supported
        if ($this->supportsCacheTags($store)) {
            $store->tags(['setanjo', 'settings'])->forget($cacheKey);
        } else {
            // Clear specific cache key
            $store->forget($cacheKey);
        }
    }

    /**
     * Update cache with current loaded settings
     */
    protected function updateCache($tenant): void
    {
        if (! config('setanjo.cache.enabled', true)) {
            return;
        }

        $tenantKey = $this->getTenantKey($tenant);

        // Only update if we have loaded settings for this tenant
        if (! isset($this->loadedSettings[$tenantKey])) {
            return;
        }

        $cacheKey = $this->getCacheKey($tenant);
        $store = Cache::store(config('setanjo.cache.store'));
        $ttl = config('setanjo.cache.ttl', 3600);

        // Use tags if supported
        if ($this->supportsCacheTags($store)) {
            $store->tags(['setanjo', 'settings'])
                ->put($cacheKey, $this->loadedSettings[$tenantKey], $ttl);
        } else {
            $store->put($cacheKey, $this->loadedSettings[$tenantKey], $ttl);
        }
    }
}

<?php

namespace Ahs12\Setanjo\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for per-scope setting payloads.
 *
 * Keys embed a global version number: a write forgets the single scope key, while
 * `flush()` bumps the version so every scope key is invalidated at once. Works on
 * every cache driver (no tags needed).
 */
class SettingsCache
{
    public function enabled(): bool
    {
        return (bool) config('setanjo.cache.enabled', false);
    }

    public function remember(string $scopeKey, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        return $this->store()->remember($this->key($scopeKey), $this->ttl(), $callback);
    }

    /**
     * Fetch a cached payload without computing it. Returns null on a miss.
     */
    public function get(string $scopeKey): mixed
    {
        return $this->enabled() ? $this->store()->get($this->key($scopeKey)) : null;
    }

    public function put(string $scopeKey, mixed $payload): void
    {
        if ($this->enabled()) {
            $this->store()->put($this->key($scopeKey), $payload, $this->ttl());
        }
    }

    public function forget(string $scopeKey): void
    {
        if ($this->enabled()) {
            $this->store()->forget($this->key($scopeKey));
        }
    }

    /**
     * Invalidate every scope at once by bumping the global version.
     */
    public function flush(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $store = $this->store();
        $store->forever($this->versionKey(), $this->version() + 1);
    }

    public function key(string $scopeKey): string
    {
        return "{$this->prefix()}:v{$this->version()}:settings:{$scopeKey}";
    }

    public function version(): int
    {
        return (int) $this->store()->get($this->versionKey(), 1);
    }

    protected function versionKey(): string
    {
        return "{$this->prefix()}:version";
    }

    protected function prefix(): string
    {
        return (string) config('setanjo.cache.prefix', 'setanjo');
    }

    protected function ttl(): int
    {
        return (int) config('setanjo.cache.ttl', 3600);
    }

    protected function store(): Repository
    {
        return Cache::store(config('setanjo.cache.store'));
    }
}

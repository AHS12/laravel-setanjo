<?php

namespace Ahs12\Setanjo\Contracts;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Setting;
use Illuminate\Support\Collection;

interface SettingsRepositoryInterface
{
    public function get(string $key, $default = null, $tenant = null);

    /**
     * Store a value. When $type is given it is used as-is, otherwise it is inferred from the value.
     */
    public function set(string $key, $value, $tenant = null, ?SettingType $type = null): void;

    public function has(string $key, $tenant = null): bool;

    public function forget(string $key, $tenant = null): void;

    public function all($tenant = null): Collection;

    public function flush($tenant = null): void;

    /**
     * The stored row for a key as a plain array, or null when nothing is stored.
     *
     * Shape: value, type, meta, group_id, sort_order, is_locked, description.
     *
     * @return array<string, mixed>|null
     */
    public function record(string $key, $tenant = null): ?array;

    /**
     * Every stored row for a scope, keyed by setting key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function records($tenant = null): array;

    /**
     * Load several scopes in as few queries as possible (null = global).
     *
     * @param  array<int, mixed>  $tenants
     */
    public function loadMany(array $tenants): void;

    public function find(string $key, $tenant = null): ?Setting;

    /**
     * Update row attributes (meta, group_id, sort_order, is_locked, description).
     *
     * When the row does not exist it is created from $createWith (value + type).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{value?: mixed, type?: SettingType}  $createWith
     */
    public function updateAttributes(string $key, array $attributes, $tenant = null, array $createWith = []): void;

    public function forgetCache($tenant = null): void;

    public function flushCache(): void;

    /**
     * Drop in-memory state so the next read goes back to the cache/database.
     */
    public function reset(): void;
}

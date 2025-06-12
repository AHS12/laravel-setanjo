<?php

namespace Ahs12\Setanjo\Contracts;

use Illuminate\Support\Collection;

interface SettingsRepositoryInterface
{
    public function get(string $key, $default = null, $tenant = null);

    public function set(string $key, $value, $tenant = null): void;

    public function has(string $key, $tenant = null): bool;

    public function forget(string $key, $tenant = null): void;

    public function all($tenant = null): Collection;

    public function flush($tenant = null): void;
}

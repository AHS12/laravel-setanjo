<?php

namespace Ahs12\Setanjo\Repositories;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Support\SettingsCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    /**
     * In-memory rows per scope key: [scopeKey => [settingKey => record]].
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $loadedSettings = [];

    protected SettingsCache $cache;

    public function __construct(?SettingsCache $cache = null)
    {
        $this->cache = $cache ?? new SettingsCache;
    }

    public function get(string $key, $default = null, $tenant = null)
    {
        $record = $this->record($key, $tenant);

        return $record === null ? $default : $record['value'];
    }

    public function set(string $key, $value, $tenant = null, ?SettingType $type = null): void
    {
        $setting = $this->findOrNew($key, $tenant);

        $setting->type = $type ?? SettingType::fromValue($value);
        $setting->value = $value;
        $setting->save();

        $this->remember($setting, $tenant);
    }

    public function has(string $key, $tenant = null): bool
    {
        return $this->record($key, $tenant) !== null;
    }

    public function forget(string $key, $tenant = null): void
    {
        Setting::forTenant($tenant)->where('key', $key)->delete();

        $this->loadSettingsForTenant($tenant);
        unset($this->loadedSettings[$this->getTenantKey($tenant)][$key]);

        $this->forgetCache($tenant);
    }

    public function all($tenant = null): Collection
    {
        return collect($this->records($tenant))->map(fn (array $record) => $record['value']);
    }

    public function flush($tenant = null): void
    {
        Setting::forTenant($tenant)->delete();

        $this->loadedSettings[$this->getTenantKey($tenant)] = [];

        $this->forgetCache($tenant);
    }

    public function record(string $key, $tenant = null): ?array
    {
        $this->loadSettingsForTenant($tenant);

        return $this->loadedSettings[$this->getTenantKey($tenant)][$key] ?? null;
    }

    public function records($tenant = null): array
    {
        $this->loadSettingsForTenant($tenant);

        return $this->loadedSettings[$this->getTenantKey($tenant)] ?? [];
    }

    public function loadMany(array $tenants): void
    {
        $misses = [];

        foreach ($tenants as $tenant) {
            $tenantKey = $this->getTenantKey($tenant);

            if (isset($this->loadedSettings[$tenantKey]) || isset($misses[$tenantKey])) {
                continue;
            }

            $cached = $this->cache->get($tenantKey);

            if (is_array($cached)) {
                $this->loadedSettings[$tenantKey] = $cached;

                continue;
            }

            $misses[$tenantKey] = $tenant;
        }

        if ($misses === []) {
            return;
        }

        $column = config('setanjo.tenantable_column', 'tenantable');
        $grouped = array_fill_keys(array_keys($misses), []);

        // (scope A) OR (scope B) ... wrapped in one group so outer conditions still apply to all
        Setting::query()
            ->where(function (Builder $query) use ($misses) {
                foreach ($misses as $tenant) {
                    $query->orWhere(fn (Builder $scope) => $scope->forTenant($tenant));
                }
            })
            ->get()
            ->each(function (Setting $setting) use (&$grouped, $column) {
                $type = $setting->getAttribute($column.'_type');
                $tenantKey = $type === null ? 'global' : $type.':'.$setting->getAttribute($column.'_id');

                $grouped[$tenantKey][$setting->key] = $this->toRecord($setting);
            });

        foreach ($misses as $tenantKey => $tenant) {
            $this->loadedSettings[$tenantKey] = $grouped[$tenantKey] ?? [];
            $this->cache->put($tenantKey, $this->loadedSettings[$tenantKey]);
        }
    }

    public function find(string $key, $tenant = null): ?Setting
    {
        return Setting::forTenant($tenant)->where('key', $key)->first();
    }

    public function updateAttributes(string $key, array $attributes, $tenant = null, array $createWith = []): void
    {
        $setting = $this->findOrNew($key, $tenant);

        if (! $setting->exists) {
            $setting->type = $createWith['type'] ?? SettingType::fromValue($createWith['value'] ?? null);
            $setting->value = $createWith['value'] ?? null;
        }

        $setting->forceFill(array_intersect_key($attributes, array_flip([
            'meta', 'group_id', 'sort_order', 'is_locked', 'description',
        ])));
        $setting->save();

        $this->remember($setting, $tenant);
    }

    public function forgetCache($tenant = null): void
    {
        $this->cache->forget($this->getTenantKey($tenant));
    }

    public function flushCache(): void
    {
        $this->cache->flush();
        $this->loadedSettings = [];
    }

    public function reset(): void
    {
        $this->loadedSettings = [];
    }

    protected function loadSettingsForTenant($tenant): void
    {
        if (! isset($this->loadedSettings[$this->getTenantKey($tenant)])) {
            $this->loadMany([$tenant]);
        }
    }

    /**
     * Put a freshly saved row into memory and drop the scope's cached payload.
     */
    protected function remember(Setting $setting, $tenant): void
    {
        $this->loadSettingsForTenant($tenant);
        $this->loadedSettings[$this->getTenantKey($tenant)][$setting->key] = $this->toRecord($setting);

        $this->forgetCache($tenant);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRecord(Setting $setting): array
    {
        return [
            'value' => $setting->value,
            'type' => $setting->getRawOriginal('type') ?: SettingType::STRING->value,
            'meta' => $setting->meta,
            'group_id' => $setting->group_id,
            'sort_order' => (int) $setting->sort_order,
            'is_locked' => (bool) $setting->is_locked,
            'description' => $setting->description,
        ];
    }

    protected function findOrNew(string $key, $tenant = null): Setting
    {
        $setting = $this->find($key, $tenant);

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
}

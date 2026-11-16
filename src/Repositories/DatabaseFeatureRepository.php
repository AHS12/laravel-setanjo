<?php

namespace Ahs12\Setanjo\Repositories;

use Ahs12\Setanjo\Contracts\FeatureRepositoryInterface;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Support\ScopeKey;
use Ahs12\Setanjo\Support\SettingsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseFeatureRepository implements FeatureRepositoryInterface
{
    protected const FLAGS_KEY = 'features:flags';

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $flags = null;

    /** @var array<string, array<string, bool>> scopeKey => [featureKey => enabled] */
    protected array $overrides = [];

    protected SettingsCache $cache;

    public function __construct(?SettingsCache $cache = null)
    {
        $this->cache = $cache ?? new SettingsCache;
    }

    public function flags(): array
    {
        if ($this->flags !== null) {
            return $this->flags;
        }

        return $this->flags = $this->cache->remember(self::FLAGS_KEY, function () {
            return Feature::query()->ordered()->get()
                ->mapWithKeys(fn (Feature $feature) => [$feature->key => $this->toRecord($feature)])
                ->all();
        });
    }

    public function flag(string $key): ?array
    {
        return $this->flags()[$key] ?? null;
    }

    public function overrides(Model $scope): array
    {
        $this->loadOverrides([$scope]);

        return $this->overrides[$this->scopeKey($scope)] ?? [];
    }

    public function loadOverrides(array $scopes): void
    {
        $misses = [];

        foreach ($scopes as $scope) {
            $scopeKey = $this->scopeKey($scope);

            if (isset($this->overrides[$scopeKey]) || isset($misses[$scopeKey])) {
                continue;
            }

            $cached = $this->cache->get('features:'.$scopeKey);

            if (is_array($cached)) {
                $this->overrides[$scopeKey] = $cached;

                continue;
            }

            // A UUID/ULID key cannot be in an integer scope_id column: nothing stored, nothing to query.
            if (! ScopeKey::fits(new FeatureOverride, 'scope_id', $scope->getKey())) {
                $this->overrides[$scopeKey] = [];

                continue;
            }

            $misses[$scopeKey] = $scope;
        }

        if ($misses === []) {
            return;
        }

        $overrides = (new FeatureOverride)->getTable();
        $features = (new Feature)->getTable();
        $loaded = array_fill_keys(array_keys($misses), []);

        DB::table($overrides)
            ->join($features, "{$features}.id", '=', "{$overrides}.feature_id")
            ->where(function (Builder $query) use ($misses, $overrides) {
                foreach ($misses as $scope) {
                    $query->orWhere(fn (Builder $where) => $where
                        ->where("{$overrides}.scope_type", $scope::class)
                        ->where("{$overrides}.scope_id", $scope->getKey()));
                }
            })
            ->get(["{$overrides}.scope_type", "{$overrides}.scope_id", "{$overrides}.enabled", "{$features}.key"])
            ->each(function (object $row) use (&$loaded) {
                $loaded[$row->scope_type.':'.$row->scope_id][$row->key] = (bool) $row->enabled;
            });

        foreach ($misses as $scopeKey => $scope) {
            $this->overrides[$scopeKey] = $loaded[$scopeKey] ?? [];
            $this->cache->put('features:'.$scopeKey, $this->overrides[$scopeKey]);
        }
    }

    public function setGlobal(string $key, ?bool $enabled, array $createWith = []): void
    {
        $feature = $this->findOrNew($key, $createWith);
        $feature->enabled = $enabled;
        $feature->save();

        $this->forgetFlags();
    }

    public function setOverride(string $key, Model $scope, bool $enabled, array $createWith = []): void
    {
        $feature = $this->findOrNew($key, $createWith);

        if (! $feature->exists) {
            $feature->save();
            $this->forgetFlags();
        }

        FeatureOverride::query()->updateOrCreate(
            ['feature_id' => $feature->id, 'scope_type' => $scope::class, 'scope_id' => $scope->getKey()],
            ['enabled' => $enabled],
        );

        $this->forgetCache($scope);
    }

    public function forgetOverride(string $key, Model $scope): bool
    {
        $feature = $this->find($key);

        if ($feature === null || ! ScopeKey::fits(new FeatureOverride, 'scope_id', $scope->getKey())) {
            return false;
        }

        $deleted = FeatureOverride::query()
            ->where('feature_id', $feature->id)
            ->where('scope_type', $scope::class)
            ->where('scope_id', $scope->getKey())
            ->delete();

        $this->forgetCache($scope);

        return $deleted > 0;
    }

    public function updateAttributes(string $key, array $attributes, array $createWith = []): void
    {
        $feature = $this->findOrNew($key, $createWith);
        $feature->fill(array_intersect_key($attributes, array_flip([
            'name', 'description', 'group_id', 'sort_order', 'meta',
            'enabled_from', 'enabled_until', 'rollout', 'requires', 'archived_at',
        ])));
        $feature->save();

        $this->forgetFlags();
    }

    public function delete(string $key): void
    {
        $feature = $this->find($key);

        if ($feature === null) {
            return;
        }

        FeatureOverride::query()->where('feature_id', $feature->id)->delete();
        $feature->delete();

        $this->flushCache();
    }

    public function find(string $key): ?Feature
    {
        return Feature::query()->where('key', $key)->first();
    }

    public function overrideCounts(): array
    {
        $overrides = (new FeatureOverride)->getTable();
        $features = (new Feature)->getTable();

        return DB::table($overrides)
            ->join($features, "{$features}.id", '=', "{$overrides}.feature_id")
            ->groupBy("{$features}.key")
            ->selectRaw("{$features}.key as feature_key, COUNT(*) as aggregate")
            ->pluck('aggregate', 'feature_key')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function overridesOf(string $key): Collection
    {
        $feature = $this->find($key);

        return $feature === null
            ? collect()
            : FeatureOverride::query()->where('feature_id', $feature->id)->with('scope')->get()->toBase();
    }

    public function forgetCache(?Model $scope = null): void
    {
        if ($scope === null) {
            $this->forgetFlags();

            return;
        }

        $scopeKey = $this->scopeKey($scope);
        unset($this->overrides[$scopeKey]);
        $this->cache->forget('features:'.$scopeKey);
    }

    public function flushCache(): void
    {
        $this->cache->flush();
        $this->reset();
    }

    public function reset(): void
    {
        $this->flags = null;
        $this->overrides = [];
    }

    protected function forgetFlags(): void
    {
        $this->flags = null;
        $this->cache->forget(self::FLAGS_KEY);
    }

    /**
     * @param  array<string, mixed>  $createWith
     */
    protected function findOrNew(string $key, array $createWith): Feature
    {
        return $this->find($key) ?? (new Feature)->fill([
            'key' => $key,
            ...array_intersect_key($createWith, array_flip(['name', 'description', 'group_id', 'sort_order', 'meta'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRecord(Feature $feature): array
    {
        // Until the 3.2 migration runs the rule columns do not exist. Read them only when loaded,
        // so flags keep working in between (strict mode would throw on a missing attribute).
        $rule = fn (string $column): mixed => array_key_exists($column, $feature->getAttributes()) ? $feature->getAttribute($column) : null;

        return [
            'id' => $feature->id,
            'enabled' => $feature->enabled,
            'name' => $feature->name,
            'description' => $feature->description,
            'group_id' => $feature->group_id,
            'sort_order' => (int) $feature->sort_order,
            'meta' => $feature->meta,
            // Strings, not Carbon, so the cached record stays plain data.
            'enabled_from' => $rule('enabled_from')?->toIso8601String(),
            'enabled_until' => $rule('enabled_until')?->toIso8601String(),
            'rollout' => $rule('rollout'),
            'requires' => $rule('requires') ?? [],
            'archived_at' => $rule('archived_at')?->toIso8601String(),
        ];
    }

    protected function scopeKey(Model $scope): string
    {
        return $scope::class.':'.$scope->getKey();
    }
}

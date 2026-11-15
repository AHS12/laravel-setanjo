<?php

namespace Ahs12\Setanjo\Dashboard;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\Support\ScopeKey;
use Ahs12\Setanjo\ValueObjects\ResolvedValue;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the rows the dashboard shows: every defined or stored setting and feature flag,
 * with effective meta, group, order, override counts and sensitive values masked.
 */
class Catalog
{
    /** @var array<string, int>|null */
    protected ?array $groupIds = null;

    /** @var array<string, array<string, mixed>> */
    protected array $presented = [];

    public function __construct(
        protected SetanjoManager $manager,
        protected ScopeRegistry $scopes,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        $definitions = $this->manager->definitions();
        $records = $this->manager->repository()->records(null);
        $counts = $this->settingOverrideCounts();
        $order = array_flip(array_keys($definitions->settings()));

        $keys = array_unique([...array_keys($definitions->settings()), ...array_keys($records)]);
        // Rows of keys defined as features are flags waiting for setanjo:migrate-feature-flags (see drift).
        $keys = array_filter($keys, fn (string $key) => $definitions->get($key)?->isFeature() !== true);

        $rows = [];
        foreach ($keys as $key) {
            $rows[] = $this->settingRow($key, $definitions->get($key), $records[$key] ?? null, $counts[$key] ?? 0, $order[$key] ?? count($order));
        }

        return $this->sorted($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function setting(string $key): ?array
    {
        $definition = $this->manager->definitions()->get($key);
        $record = $this->manager->repository()->record($key, null);

        if ($definition?->isFeature() || ($definition === null && $record === null)) {
            return null;
        }

        return $this->settingRow($key, $definition, $record, $this->settingOverrideCounts()[$key] ?? 0, 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function features(): array
    {
        $definitions = $this->manager->definitions()->features();
        $flags = $this->manager->featureRepository()->flags();
        $counts = $this->manager->featureRepository()->overrideCounts();
        $order = array_flip(array_keys($definitions));

        $rows = [];
        foreach (array_unique([...array_keys($definitions), ...array_keys($flags)]) as $key) {
            $rows[] = $this->featureRow($key, $definitions[$key] ?? null, $flags[$key] ?? null, $counts[$key] ?? 0, $order[$key] ?? count($order));
        }

        return $this->sorted($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function feature(string $key): ?array
    {
        $definition = $this->manager->definitions()->get($key);
        $flag = $this->manager->featureRepository()->flag($key);

        if ($flag === null && $definition?->isFeature() !== true) {
            return null;
        }

        return $this->featureRow($key, $definition, $flag, $this->manager->featureRepository()->overrideCounts()[$key] ?? 0, 0);
    }

    /**
     * Every setting and flag as seen by one scope: its own override and the effective value.
     *
     * For a record that no longer exists (a ScopeRegistry ghost), only its stored overrides are listed and
     * nothing is resolved, so they can be reviewed and cleared.
     *
     * @return array{scope: array<string, mixed>, settings: array<int, array<string, mixed>>, features: array<int, array<string, mixed>>}
     */
    public function forScope(Model $scope): array
    {
        $missing = $this->scopes->isGhost($scope);
        $this->presented = [];
        $repository = $this->manager->repository();
        $features = $this->manager->featureRepository();
        $overrides = $features->overrides($scope);
        $settings = [];
        $flags = [];

        foreach ($this->settings() as $row) {
            $stored = $repository->record($row['key'], $scope);

            if ($missing && $stored === null) {
                continue;
            }

            $settings[] = [
                ...$this->slim($row),
                'allowed' => $this->allows($row['key'], $scope),
                'overridden' => $stored !== null,
                'override' => $stored === null ? null : $this->mask($row, $stored['value']),
                ...($missing ? $this->unresolved() : [
                    ...$this->effective($row, $this->manager->resolve($row['key'], $scope)),
                    'chain' => $this->chain($row, $scope, function (?Model $level) use ($repository, $row) {
                        $record = $repository->record($row['key'], $level);

                        return $record === null ? [false, null] : [true, $record['value']];
                    }),
                ]),
            ];
        }

        foreach ($this->features() as $row) {
            $override = $overrides[$row['key']] ?? null;

            if ($missing && $override === null) {
                continue;
            }

            $flags[] = [
                ...$this->slim($row),
                'allowed' => $this->allows($row['key'], $scope),
                'override' => $override,
                ...($missing ? $this->unresolved() : [
                    ...$this->effective($row, $this->manager->resolveFeature($row['key'], $scope)),
                    'chain' => $this->chain($row, $scope, function (?Model $level) use ($features, $row) {
                        if ($level === null) {
                            $global = $features->flag($row['key'])['enabled'] ?? null;

                            return [$global !== null, $global === null ? null : (bool) $global];
                        }

                        $levelOverrides = $features->overrides($level);

                        return array_key_exists($row['key'], $levelOverrides) ? [true, $levelOverrides[$row['key']]] : [false, null];
                    }),
                ]),
            ];
        }

        return [
            'scope' => $this->scopes->present($scope),
            'settings' => $settings,
            'features' => $flags,
        ];
    }

    /**
     * Whether anything is stored for this scope (settings or flag overrides).
     */
    public function hasOverrides(string $class, int|string $id): bool
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return (ScopeKey::fits(new Setting, $column.'_id', $id)
                && Setting::query()->toBase()->where($column.'_type', $class)->where($column.'_id', $id)->exists())
            || (ScopeKey::fits(new FeatureOverride, 'scope_id', $id)
                && FeatureOverride::query()->where('scope_type', $class)->where('scope_id', $id)->exists());
    }

    /**
     * Records of a scope type that have at least one setting or flag override, by id, one page at a time.
     *
     * @return array{data: array<int, array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function overriddenRecords(string $alias, int $page = 1, int $perPage = 50): array
    {
        $class = $this->scopes->type($alias)['class'];
        [$settings, $flags] = $this->overrideCountsFor($class);

        $ids = $this->idsOf($settings, $flags);
        $total = $ids->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $records = [];
        foreach ($ids->forPage($page, $perPage) as $id) {
            $records[] = [
                ...(array) $this->scopes->reference($class, $id),
                'settings' => (int) ($settings[$id] ?? 0),
                'features' => (int) ($flags[$id] ?? 0),
            ];
        }

        return ['data' => $records, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage];
    }

    /**
     * Overrides stored for records that no longer exist, and for model classes the dashboard
     * does not know (not in `setanjo.scopes` or the tenancy config).
     *
     * @return array{missing_records: array<int, array<string, mixed>>, unknown_scope_types: array<int, array<string, mixed>>}
     */
    public function orphans(): array
    {
        $missing = [];
        $unknown = [];

        foreach ($this->scopeClasses() as $class) {
            [$settings, $flags] = $this->overrideCountsFor($class);
            $ids = $this->idsOf($settings, $flags);
            $alias = $this->scopes->aliasOf($class);

            if ($alias === null) {
                $unknown[] = ['class' => $class, 'records' => $ids->count(), 'settings' => (int) $settings->sum(), 'features' => (int) $flags->sum()];

                continue;
            }

            foreach ($this->scopes->missingIds($alias, $ids->all()) as $id) {
                $missing[] = [
                    ...(array) $this->scopes->reference($class, $id),
                    'settings' => (int) ($settings[$id] ?? 0),
                    'features' => (int) ($flags[$id] ?? 0),
                ];
            }
        }

        return ['missing_records' => $missing, 'unknown_scope_types' => $unknown];
    }

    /**
     * The records that override one setting or flag, newest first, a page at a time,
     * optionally limited to one scope type.
     *
     * @return array{data: array<int, array<string, mixed>>, page: int, per_page: int, total: int, last_page: int}
     */
    public function keyOverrides(string $key, bool $feature, ?string $alias = null, int $page = 1, int $perPage = 50): array
    {
        $class = $alias !== null ? $this->scopes->type($alias)['class'] : null;

        if ($feature) {
            $typeColumn = 'scope_type';
            $idColumn = 'scope_id';
            $query = FeatureOverride::query()->where('feature_id', Feature::query()->where('key', $key)->value('id') ?? 0);
        } else {
            $column = config('setanjo.tenantable_column', 'tenantable');
            $typeColumn = $column.'_type';
            $idColumn = $column.'_id';
            $query = Setting::query()->where('key', $key)->whereNotNull($typeColumn);
        }

        if ($class !== null) {
            // The tenant column name is configurable, so filter on the base builder.
            $query->getQuery()->where($typeColumn, $class);
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));

        /** @var array<int, Model> $items */
        $items = $paginator->items();
        $references = $this->scopes->references(array_map(
            fn (Model $item) => [(string) $item->getAttribute($typeColumn), $item->getAttribute($idColumn)],
            $items,
        ));
        $row = $feature ? [] : ($this->setting($key) ?? []);

        return [
            'data' => array_map(fn (Model $item) => [
                'scope' => $references[$item->getAttribute($typeColumn).':'.$item->getAttribute($idColumn)],
                'value' => $feature ? (bool) $item->getAttribute('enabled') : $this->mask($row, $item->getAttribute('value')),
                'updated_at' => $item->getAttribute('updated_at') instanceof CarbonInterface
                    ? $item->getAttribute('updated_at')->toIso8601String()
                    : null,
            ], $items),
            'page' => $paginator->currentPage(),
            'per_page' => $perPage,
            'total' => $paginator->total(),
            'last_page' => max(1, $paginator->lastPage()),
        ];
    }

    /**
     * The numbers on the overview screen.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $settings = collect($this->settings());
        $features = collect($this->features());
        $scopeTypes = [];

        foreach ($this->scopeClasses() as $class) {
            [$settingCounts, $flagCounts] = $this->overrideCountsFor($class);
            $alias = $this->scopes->aliasOf($class);

            $scopeTypes[] = [
                'type' => $alias,
                'class' => $class,
                'name' => $alias !== null ? $this->scopes->type($alias)['name'] : class_basename($class),
                'records' => $this->idsOf($settingCounts, $flagCounts)->count(),
                'settings' => (int) $settingCounts->sum(),
                'features' => (int) $flagCounts->sum(),
            ];
        }

        $top = fn (Collection $rows, string $kind) => $rows->where('override_count', '>', 0)
            ->sortByDesc('override_count')
            ->take(5)
            ->map(fn (array $row) => ['kind' => $kind, 'key' => $row['key'], 'label' => $row['label'], 'overrides' => $row['override_count']])
            ->values()
            ->all();

        return [
            'settings' => [
                'total' => $settings->count(),
                'stored' => $settings->where('stored', true)->count(),
                'undefined' => $settings->where('defined', false)->count(),
                'readonly' => $settings->where('readonly', true)->count(),
                'overridden' => $settings->where('override_count', '>', 0)->count(),
            ],
            'features' => [
                'total' => $features->count(),
                'on' => $features->where('enabled', true)->count(),
                'off' => $features->where('enabled', false)->count(),
                'overridden' => $features->where('override_count', '>', 0)->count(),
            ],
            'groups' => SettingGroup::query()->count(),
            'scopes' => $scopeTypes,
            'most_overridden' => [...$top($settings, 'setting'), ...$top($features, 'feature')],
        ];
    }

    /**
     * Whether a scope may override the key (the definition's `scopes()`; empty allows any).
     */
    public function allows(string $key, Model $scope): bool
    {
        $allowed = $this->manager->definitions()->get($key)?->scopes() ?? [];

        return $allowed === [] || $this->manager->scopeResolver()->isAllowed($scope, $allowed);
    }

    /**
     * Every model class that has a setting or flag override stored.
     *
     * @return Collection<int, string>
     */
    protected function scopeClasses(): Collection
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return Setting::query()->toBase()->whereNotNull($column.'_type')->distinct()->pluck($column.'_type')
            ->merge(FeatureOverride::query()->distinct()->pluck('scope_type'))
            ->map(fn ($class) => (string) $class)
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Every level a value is looked up in, most specific first: the record's scope chain
     * (e.g. user → branch → tenant), global, then the code default. `set` marks the levels
     * that store a value; the first one that does is where the effective value comes from.
     *
     * @param  array<string, mixed>  $row
     * @param  Closure(?Model): array{0: bool, 1: mixed}  $lookup
     * @return array<int, array<string, mixed>>
     */
    protected function chain(array $row, Model $scope, Closure $lookup): array
    {
        $definition = $this->manager->definitions()->get($row['key']);
        $levels = [];

        foreach ($this->manager->scopeResolver()->chain($scope, $definition?->scopes() ?? []) as $level) {
            [$set, $value] = $lookup($level);
            $levels[] = ['level' => 'scope', 'scope' => $this->presentOnce($level), 'set' => $set, 'value' => $set ? $this->mask($row, $value) : null];
        }

        [$set, $value] = $lookup(null);
        $levels[] = ['level' => 'global', 'scope' => null, 'set' => $set, 'value' => $set ? $this->mask($row, $value) : null];
        $levels[] = ['level' => 'default', 'scope' => null, 'set' => true, 'value' => $row['default'] ?? null];

        return $levels;
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentOnce(Model $scope): array
    {
        return $this->presented[$scope::class.':'.$scope->getKey()] ??= $this->scopes->present($scope);
    }

    /**
     * Mask a value of a setting row marked sensitive.
     *
     * @param  array<string, mixed>  $row
     */
    public function mask(array $row, mixed $value): mixed
    {
        return ($row['sensitive'] ?? false) && $value !== null ? null : $value;
    }

    public function groupId(?SettingDefinition $definition): ?int
    {
        $slug = DefinitionRegistry::groupSlug($definition);

        if ($slug === null) {
            return null;
        }

        $this->groupIds ??= SettingGroup::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();

        return $this->groupIds[$slug] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function settingRow(string $key, ?SettingDefinition $definition, ?array $record, int $overrides, int $position): array
    {
        $definitionMeta = $definition?->meta() ?? [];
        $storedMeta = $record['meta'] ?? null;
        $meta = array_replace($definitionMeta, $storedMeta ?? []);
        $locked = (bool) ($record['is_locked'] ?? false);
        $sensitive = (bool) ($meta['sensitive'] ?? false);
        $value = $record !== null ? $record['value'] : $definition?->default();

        return [
            'key' => $key,
            'label' => $definition?->label() ?? $key,
            'description' => $record['description'] ?? $definition?->description(),
            'type' => $definition?->type()->value ?? $record['type'] ?? SettingType::STRING->value,
            'defined' => $definition !== null,
            'stored' => $record !== null,
            'default' => $sensitive ? null : $definition?->default(),
            'value' => $sensitive && $value !== null ? null : $value,
            'masked' => $sensitive && $value !== null,
            'source' => $record !== null ? 'global' : 'default',
            'sensitive' => $sensitive,
            'is_locked' => $locked,
            'readonly' => $locked || (bool) ($meta['readonly'] ?? false),
            'group_id' => $record['group_id'] ?? $this->groupId($definition),
            'sort_order' => $record !== null ? (int) $record['sort_order'] : $position,
            'meta' => $meta,
            'definition_meta' => $definitionMeta,
            'stored_meta' => $storedMeta,
            'override_count' => $overrides,
            'scopes' => $this->scopeAliases($definition),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $flag
     * @return array<string, mixed>
     */
    protected function featureRow(string $key, ?SettingDefinition $definition, ?array $flag, int $overrides, int $position): array
    {
        $definitionMeta = $definition?->meta() ?? [];
        $storedMeta = $flag['meta'] ?? null;
        $default = (bool) ($definition?->default() ?? false);
        $global = $flag['enabled'] ?? null;

        return [
            'key' => $key,
            'label' => $flag['name'] ?? $definition?->label() ?? $key,
            'description' => $flag['description'] ?? $definition?->description(),
            'type' => SettingType::BOOLEAN->value,
            'defined' => $definition?->isFeature() === true,
            'stored' => $flag !== null,
            'default' => $default,
            'global' => $global === null ? null : (bool) $global,
            'enabled' => $global === null ? $default : (bool) $global,
            'source' => $global === null ? 'default' : 'global',
            'group_id' => $flag['group_id'] ?? $this->groupId($definition),
            'sort_order' => $flag !== null ? (int) $flag['sort_order'] : $position,
            'meta' => array_replace($definitionMeta, $storedMeta ?? []),
            'definition_meta' => $definitionMeta,
            'stored_meta' => $storedMeta,
            'override_count' => $overrides,
            'scopes' => $this->scopeAliases($definition),
        ];
    }

    /**
     * Setting and flag override counts per record id of one scope class.
     *
     * @return array{0: Collection<int|string, int>, 1: Collection<int|string, int>}
     */
    protected function overrideCountsFor(string $class): array
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        // The tenant column name is configurable, so query it on the base builder.
        $settings = Setting::query()->toBase()
            ->where($column.'_type', $class)
            ->groupBy($column.'_id')
            ->selectRaw("{$column}_id as scope_id, COUNT(*) as aggregate")
            ->pluck('aggregate', 'scope_id')
            ->map(fn ($count) => (int) $count);

        $flags = FeatureOverride::query()
            ->where('scope_type', $class)
            ->groupBy('scope_id')
            ->selectRaw('scope_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'scope_id')
            ->map(fn ($count) => (int) $count);

        return [$settings, $flags];
    }

    /**
     * Distinct record ids across both count maps, in natural order (1, 2, 10; UUIDs alphabetically).
     *
     * @param  Collection<int|string, int>  $settings
     * @param  Collection<int|string, int>  $flags
     * @return Collection<int, int|string>
     */
    protected function idsOf(Collection $settings, Collection $flags): Collection
    {
        return $settings->keys()->merge($flags->keys())
            ->unique(fn ($id) => (string) $id)
            ->sort(fn ($a, $b) => strnatcmp((string) $a, (string) $b))
            ->values();
    }

    /**
     * @return array{effective: null, source: string, source_scope: null, missing: true}
     */
    protected function unresolved(): array
    {
        return ['effective' => null, 'source' => 'missing', 'source_scope' => null, 'missing' => true];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function slim(array $row): array
    {
        return array_intersect_key($row, array_flip([
            'key', 'label', 'description', 'type', 'group_id', 'sort_order', 'meta', 'sensitive', 'readonly', 'is_locked', 'default',
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function effective(array $row, ResolvedValue $resolved): array
    {
        return [
            'effective' => $this->mask($row, $resolved->value),
            'source' => $resolved->source->value,
            'source_scope' => $resolved->scope ? $this->scopes->present($resolved->scope) : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function scopeAliases(?SettingDefinition $definition): array
    {
        return array_values(array_filter(array_map(
            fn (string $class) => $this->scopes->aliasOf($class) ?? class_basename($class),
            $definition?->scopes() ?? [],
        )));
    }

    /**
     * @return array<string, int>
     */
    protected function settingOverrideCounts(): array
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return Setting::query()
            ->whereNotNull($column.'_type')
            ->groupBy('key')
            ->select('key', DB::raw('COUNT(*) as aggregate'))
            ->pluck('aggregate', 'key')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function sorted(array $rows): array
    {
        usort($rows, fn (array $a, array $b) => [$a['sort_order'], $a['label']] <=> [$b['sort_order'], $b['label']]);

        return $rows;
    }
}

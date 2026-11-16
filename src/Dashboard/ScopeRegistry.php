<?php

namespace Ahs12\Setanjo\Dashboard;

use Ahs12\Setanjo\Exceptions\DashboardException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * The scope types (models) the dashboard can manage overrides for, from `setanjo.scopes`.
 *
 * Each type gets a URL-safe alias (default: snake-cased class basename, e.g. `user`).
 */
class ScopeRegistry
{
    /** Columns tried, in order, when a type does not set `label` or `search`. */
    public const GUESSED_COLUMNS = ['name', 'title', 'label', 'email', 'slug', 'code'];

    public const FOUND = 'found';

    public const HIDDEN = 'hidden';

    public const MISSING = 'missing';

    /** @var array<string, array<string, mixed>>|null alias => config */
    protected ?array $types = null;

    /** @var array<string, array<int, string>> class => columns */
    protected array $columns = [];

    /** @var WeakMap<Model, true> */
    protected WeakMap $ghosts;

    public function __construct()
    {
        $this->ghosts = new WeakMap;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $configured = config('setanjo.scopes', []) ?: $this->fromTenancy();
        $types = [];

        foreach ($configured as $class => $options) {
            if (is_int($class)) {
                [$class, $options] = [$options, []];
            }

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $alias = $options['alias'] ?? Str::snake(class_basename($class));
            $base = $alias;
            for ($i = 2; isset($types[$alias]); $i++) {
                $alias = $base.'_'.$i;
            }

            $types[$alias] = [
                'alias' => $alias,
                'class' => $class,
                'name' => $options['name'] ?? Str::headline(class_basename($class)),
                // null = guess from the table's columns (see GUESSED_COLUMNS).
                'label' => $options['label'] ?? null,
                'search' => isset($options['search']) ? (array) $options['search'] : null,
                'description' => $options['description'] ?? null,
                'without_scopes' => (bool) ($options['without_scopes'] ?? false),
            ];
        }

        return $this->types = $types;
    }

    /**
     * Public description of every type (no closures).
     *
     * @return array<int, array{alias: string, class: string, name: string}>
     */
    public function summary(): array
    {
        return array_values(array_map(fn (array $type) => [
            'alias' => $type['alias'],
            'class' => $type['class'],
            'name' => $type['name'],
        ], $this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function type(string $alias): array
    {
        return $this->all()[$alias]
            ?? throw DashboardException::notFound(__('setanjo::dashboard.unknown_scope', ['type' => $alias]));
    }

    public function aliasOf(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        foreach ($this->all() as $alias => $type) {
            if ($type['class'] === $class) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * Records matching the term on the search columns, or on the primary key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $alias, ?string $term, int $limit = 20): array
    {
        $type = $this->type($alias);
        $query = $this->query($type);
        $model = $query->getModel();
        $term = trim((string) $term);

        if ($term !== '') {
            $columns = $this->searchColumns($type);
            $matchKey = $this->looksLikeKey($model, $term);

            if ($columns === [] && ! $matchKey) {
                return [];
            }

            $query->where(function (Builder $where) use ($model, $columns, $term, $matchKey) {
                if ($matchKey) {
                    $where->orWhere($model->getQualifiedKeyName(), $term);
                }

                foreach ($columns as $column) {
                    $where->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        return $query->limit($limit)->get()
            ->map(fn (Model $model) => $this->present($model, $alias))
            ->values()
            ->all();
    }

    /**
     * A record of the type, soft-deleted ones included.
     */
    public function find(string $alias, int|string $id): Model
    {
        $model = $this->findOrNull($alias, $id);

        if ($model !== null) {
            return $model;
        }

        $name = $this->type($alias)['name'];

        throw DashboardException::notFound($this->state($alias, $id) === self::HIDDEN
            ? __('setanjo::dashboard.scope_hidden', ['type' => $name, 'id' => $id])
            : __('setanjo::dashboard.scope_not_found', ['type' => $name, 'id' => $id]));
    }

    /**
     * A record of the type as the dashboard sees it (global scopes applied unless
     * `without_scopes`), soft-deleted ones included.
     */
    public function findOrNull(string $alias, int|string $id): ?Model
    {
        $query = $this->query($this->type($alias), withTrashed: true);

        if (! $this->looksLikeKey($query->getModel(), (string) $id)) {
            return null;
        }

        return $query->whereKey($id)->first();
    }

    /**
     * The records of the given ids that the dashboard can see, keyed by the id as a string.
     * Ids that are malformed, deleted or hidden by a global scope are left out.
     *
     * @param  array<int, int|string>  $ids
     * @return array<string, Model>
     */
    public function findMany(string $alias, array $ids): array
    {
        $query = $this->query($this->type($alias), withTrashed: true);
        $model = $query->getModel();
        $candidates = array_values(array_filter($ids, fn ($id) => $this->looksLikeKey($model, (string) $id)));
        $found = [];

        foreach (array_chunk($candidates, 500) as $chunk) {
            foreach ((clone $query)->whereKey($chunk)->get() as $record) {
                $found[(string) $record->getKey()] = $record;
            }
        }

        return $found;
    }

    /**
     * Present stored scope references in bulk: one query per scope class instead of one per record.
     *
     * @param  array<int, array{0: string, 1: int|string}>  $pairs  [class, id]
     * @return array<string, array<string, mixed>> keyed by "class:id"
     */
    public function references(array $pairs): array
    {
        $byClass = [];
        foreach ($pairs as [$class, $id]) {
            $byClass[$class][(string) $id] = $id;
        }

        $presented = [];
        foreach ($byClass as $class => $ids) {
            $alias = $this->aliasOf($class);
            $found = $alias !== null ? $this->findMany($alias, array_values($ids)) : [];

            foreach ($ids as $key => $id) {
                $presented[$class.':'.$key] = isset($found[$key])
                    ? $this->present($found[$key], $alias)
                    : (array) $this->reference($class, $id);
            }
        }

        return $presented;
    }

    /**
     * Whether the id can be a key of the type at all (digits for integer keys).
     */
    public function acceptsKey(string $alias, int|string $id): bool
    {
        /** @var class-string<Model> $class */
        $class = $this->type($alias)['class'];

        return $this->looksLikeKey(new $class, (string) $id);
    }

    /**
     * FOUND, HIDDEN (exists, but a global scope such as a tenant scope filters it out), or MISSING (deleted).
     */
    public function state(string $alias, int|string $id): string
    {
        if ($this->findOrNull($alias, $id) !== null) {
            return self::FOUND;
        }

        return $this->missingIds($alias, [$id]) === [] ? self::HIDDEN : self::MISSING;
    }

    /**
     * An unsaved stand-in for a record that no longer exists, so its stored overrides can
     * still be read and cleared. Values are never resolved for it.
     */
    public function ghost(string $alias, int|string $id): Model
    {
        /** @var class-string<Model> $class */
        $class = $this->type($alias)['class'];
        $model = new $class;
        $model->setAttribute($model->getKeyName(), $this->isIntegerKey($model) && ctype_digit((string) $id) ? (int) $id : (string) $id);
        $model->exists = true;

        $this->ghosts[$model] = true;

        return $model;
    }

    public function isGhost(Model $model): bool
    {
        return isset($this->ghosts[$model]);
    }

    /**
     * Ids of the given ones that are gone from the table. Soft-deleted records and records
     * hidden by global scopes still exist.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int|string>
     */
    public function missingIds(string $alias, array $ids): array
    {
        /** @var class-string<Model> $class */
        $class = $this->type($alias)['class'];
        $query = $class::query()->withoutGlobalScopes();
        $model = $query->getModel();
        $found = [];

        $candidates = array_values(array_filter($ids, fn ($id) => $this->looksLikeKey($model, (string) $id)));
        foreach (array_chunk($candidates, 500) as $chunk) {
            foreach ((clone $query)->whereKey($chunk)->pluck($model->getKeyName()) as $id) {
                $found[(string) $id] = true;
            }
        }

        return array_values(array_filter($ids, fn ($id) => ! isset($found[(string) $id])));
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Model $model, ?string $alias = null): array
    {
        $alias ??= $this->aliasOf($model::class);
        $type = $alias !== null ? $this->all()[$alias] ?? null : null;
        $ghost = $this->isGhost($model);

        return [
            'type' => $alias,
            'class' => $model::class,
            'id' => $model->getKey(),
            'label' => $ghost ? $this->fallbackLabel($model::class, $model->getKey()) : $this->label($model, $type),
            'description' => ! $ghost && $type !== null && $type['description'] !== null
                ? $this->resolveLabel($model, $type['description'])
                : null,
            'state' => $ghost ? self::MISSING : self::FOUND,
            'trashed' => ! $ghost && method_exists($model, 'trashed') && $model->trashed(),
        ];
    }

    /**
     * Present a stored scope reference that may no longer exist or be visible.
     *
     * @return array<string, mixed>|null
     */
    public function reference(?string $class, int|string|null $id): ?array
    {
        if ($class === null || $id === null) {
            return null;
        }

        $alias = $this->aliasOf($class);
        $model = $alias !== null ? $this->findOrNull($alias, $id) : null;

        if ($model !== null) {
            return $this->present($model, $alias);
        }

        return [
            'type' => $alias,
            'class' => $class,
            'id' => $id,
            'label' => $this->fallbackLabel($class, $id),
            'description' => null,
            'state' => $alias !== null && $this->missingIds($alias, [$id]) === [] ? self::HIDDEN : self::MISSING,
            'trashed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $type
     * @return Builder<Model>
     */
    protected function query(array $type, bool $withTrashed = false): Builder
    {
        /** @var class-string<Model> $class */
        $class = $type['class'];
        $query = $type['without_scopes'] ? $class::query()->withoutGlobalScopes() : $class::query();

        if ($withTrashed && $this->usesSoftDeletes($class)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query;
    }

    protected function usesSoftDeletes(string $class): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    protected function isIntegerKey(Model $model): bool
    {
        return in_array($model->getKeyType(), ['int', 'integer'], true);
    }

    /**
     * Configured search columns, or the guessed ones the table actually has.
     *
     * @param  array<string, mixed>  $type
     * @return array<int, string>
     */
    protected function searchColumns(array $type): array
    {
        if ($type['search'] !== null) {
            return $type['search'];
        }

        return array_values(array_intersect(self::GUESSED_COLUMNS, $this->columnsOf($type['class'])));
    }

    /**
     * Whether a term can be a primary key value of the model: digits for integer keys,
     * anything for string keys (UUIDs, ULIDs, slugs).
     */
    protected function looksLikeKey(Model $model, string $term): bool
    {
        if ($term === '') {
            return false;
        }

        if ($this->isIntegerKey($model)) {
            return ctype_digit($term);
        }

        // HasUuids / HasUlids: only well-formed ids (Postgres rejects anything else on a uuid column).
        if (in_array($model->getKeyName(), $model->uniqueIds(), true)) {
            $traits = class_uses_recursive($model);

            if (in_array(HasUuids::class, $traits, true)) {
                return Str::isUuid($term);
            }

            if (in_array(HasUlids::class, $traits, true)) {
                return Str::isUlid($term);
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $type
     */
    protected function label(Model $model, ?array $type): string
    {
        $label = $type['label'] ?? null;

        if ($label === null) {
            $label = collect(self::GUESSED_COLUMNS)
                ->first(fn (string $column) => $model->getAttribute($column) !== null && $model->getAttribute($column) !== '');
        }

        return $label !== null
            ? ($this->resolveLabel($model, $label) ?? $this->fallbackLabel($model::class, $model->getKey()))
            : $this->fallbackLabel($model::class, $model->getKey());
    }

    protected function resolveLabel(Model $model, mixed $label): ?string
    {
        $value = $label instanceof Closure ? $label($model) : $model->getAttribute((string) $label);

        return $value === null || $value === '' ? null : (string) $value;
    }

    protected function fallbackLabel(string $class, mixed $id): string
    {
        return Str::headline(class_basename($class)).' #'.$id;
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<int, string>
     */
    protected function columnsOf(string $class): array
    {
        if (! isset($this->columns[$class])) {
            $model = new $class;

            try {
                $this->columns[$class] = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
            } catch (Throwable) {
                $this->columns[$class] = [];
            }
        }

        return $this->columns[$class];
    }

    /**
     * @return array<int, string>
     */
    protected function fromTenancy(): array
    {
        if (config('setanjo.tenancy_mode') === 'strict') {
            return array_filter([config('setanjo.strict_tenant_model')]);
        }

        return config('setanjo.allowed_tenant_models', []);
    }
}

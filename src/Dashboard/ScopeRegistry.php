<?php

namespace Ahs12\Setanjo\Dashboard;

use Ahs12\Setanjo\Exceptions\DashboardException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The scope types (models) the dashboard can manage overrides for, from `setanjo.scopes`.
 *
 * Each type gets a URL-safe alias (default: snake-cased class basename, e.g. `user`).
 */
class ScopeRegistry
{
    /** @var array<string, array<string, mixed>>|null alias => config */
    protected ?array $types = null;

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
                'label' => $options['label'] ?? 'name',
                'search' => (array) ($options['search'] ?? ['name']),
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
     * @return array<int, array<string, mixed>>
     */
    public function search(string $alias, ?string $term, int $limit = 20): array
    {
        $type = $this->type($alias);
        $query = $this->query($type);
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $where) use ($type, $term) {
                if (ctype_digit($term)) {
                    $where->orWhere($where->getModel()->getQualifiedKeyName(), (int) $term);
                }

                foreach ($type['search'] as $column) {
                    $where->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        return $query->limit($limit)->get()
            ->map(fn (Model $model) => $this->present($model, $alias))
            ->values()
            ->all();
    }

    public function find(string $alias, int|string $id): Model
    {
        $type = $this->type($alias);

        return $this->query($type)->whereKey($id)->first()
            ?? throw DashboardException::notFound(__('setanjo::dashboard.scope_not_found', ['type' => $type['name'], 'id' => $id]));
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Model $model, ?string $alias = null): array
    {
        $alias ??= $this->aliasOf($model::class);
        $type = $alias !== null ? $this->all()[$alias] ?? null : null;

        return [
            'type' => $alias,
            'class' => $model::class,
            'id' => $model->getKey(),
            'label' => $this->label($model, $type['label'] ?? 'name'),
            'description' => $type !== null && $type['description'] !== null
                ? $this->label($model, $type['description'], allowEmpty: true)
                : null,
        ];
    }

    /**
     * Present a stored scope reference that may no longer exist.
     *
     * @return array<string, mixed>|null
     */
    public function reference(?string $class, int|string|null $id): ?array
    {
        if ($class === null || $id === null) {
            return null;
        }

        $alias = $this->aliasOf($class);
        $model = $alias !== null ? $this->query($this->all()[$alias])->whereKey($id)->first() : null;

        return $model !== null ? $this->present($model, $alias) : [
            'type' => $alias,
            'class' => $class,
            'id' => $id,
            'label' => class_basename($class).' #'.$id,
            'description' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $type
     * @return Builder<Model>
     */
    protected function query(array $type): Builder
    {
        /** @var class-string<Model> $class */
        $class = $type['class'];
        $query = $class::query();

        return $type['without_scopes'] ? $query->withoutGlobalScopes() : $query;
    }

    protected function label(Model $model, mixed $label, bool $allowEmpty = false): ?string
    {
        $value = $label instanceof Closure ? $label($model) : $model->getAttribute((string) $label);

        if ($value === null || $value === '') {
            return $allowEmpty ? null : class_basename($model).' #'.$model->getKey();
        }

        return (string) $value;
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

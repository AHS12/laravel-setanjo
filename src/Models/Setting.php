<?php

namespace Ahs12\Setanjo\Models;

use Ahs12\Setanjo\Enums\SettingType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property string|null $description
 * @property SettingType $type
 * @property int|null $group_id
 * @property int $sort_order
 * @property array<string, mixed>|null $meta
 * @property bool $is_locked
 * @property string|null $tenantable_type
 * @property int|null $tenantable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
        'group_id',
        'sort_order',
        'meta',
        'is_locked',
    ];

    protected $casts = [
        'value' => 'string',
        'type' => SettingType::class,
        'group_id' => 'integer',
        'sort_order' => 'integer',
        'meta' => 'array',
        'is_locked' => 'boolean',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable()
    {
        return config('setanjo.table') ?: $this->table;
    }

    /**
     * Get the tenantable model (polymorphic relationship)
     */
    public function tenantable(): MorphTo
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return $this->morphTo($column);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group_id');
    }

    /**
     * Read one item from this row's stored meta (dot notation supported).
     */
    public function meta(?string $item = null, mixed $default = null): mixed
    {
        $meta = $this->getAttribute('meta') ?? [];

        return $item === null ? $meta : Arr::get($meta, $item, $default);
    }

    public function getValueAttribute($value): mixed
    {
        $type = $this->currentType();

        if ($value === null || $type === null) {
            return $value;
        }

        return $type->cast($value);
    }

    public function setValueAttribute($value): void
    {
        $type = $this->currentType();

        if ($value === null) {
            $this->attributes['value'] = null;

            return;
        }

        $this->attributes['value'] = $type === null
            ? (string) $value
            : $type->serialize($value);
    }

    /**
     * Automatically detect and set the type based on value
     */
    public function setTypeFromValue($value): void
    {
        $this->type = SettingType::fromValue($value);
    }

    /**
     * The type currently assigned to the model.
     *
     * Reads the live attribute first so a type change made before the value is
     * assigned (e.g. integer -> array) serializes with the new type.
     */
    protected function currentType(): ?SettingType
    {
        $raw = $this->attributes['type'] ?? $this->getRawOriginal('type');

        if ($raw instanceof SettingType) {
            return $raw;
        }

        return $raw ? SettingType::tryFrom($raw) : null;
    }

    /**
     * Scope for global settings (no tenant)
     */
    public function scopeGlobal($query)
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return $query->whereNull($column.'_type')
            ->whereNull($column.'_id');
    }

    /**
     * Scope for specific tenant
     */
    public function scopeForTenant($query, $tenant)
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        if ($tenant === null) {
            return $query->global();
        }

        return $query->where($column.'_type', get_class($tenant))
            ->where($column.'_id', $tenant->getKey());
    }

    /**
     * Scope by raw morph type/id (null type = global), e.g. from an export file.
     */
    public function scopeForScopeKey($query, ?string $type, int|string|null $id)
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        if ($type === null) {
            return $query->global();
        }

        return $query->where($column.'_type', $type)->where($column.'_id', $id);
    }

    public function scopeInGroup(Builder $query, SettingGroup|int|null $group): Builder
    {
        $groupId = $group instanceof SettingGroup ? $group->getKey() : $group;

        return $groupId === null
            ? $query->whereNull('group_id')
            : $query->where('group_id', $groupId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('key');
    }
}

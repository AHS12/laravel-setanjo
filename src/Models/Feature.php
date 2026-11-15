<?php

namespace Ahs12\Setanjo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * A feature flag and its global state.
 *
 * @property int $id
 * @property string $key
 * @property string|null $name
 * @property string|null $description
 * @property bool|null $enabled
 * @property int|null $group_id
 * @property int $sort_order
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Feature extends Model
{
    protected $table = 'setanjo_features';

    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled',
        'group_id',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'group_id' => 'integer',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function getTable()
    {
        return config('setanjo.features.table') ?: $this->table;
    }

    /**
     * @return HasMany<FeatureOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(FeatureOverride::class, 'feature_id');
    }

    /**
     * @return BelongsTo<SettingGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group_id');
    }

    public function meta(?string $item = null, mixed $default = null): mixed
    {
        $meta = $this->getAttribute('meta') ?? [];

        return $item === null ? $meta : Arr::get($meta, $item, $default);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('key');
    }
}

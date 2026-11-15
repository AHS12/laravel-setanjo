<?php

namespace Ahs12\Setanjo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SettingGroup extends Model
{
    protected $table = 'setanjo_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function getTable()
    {
        return config('setanjo.groups_table') ?: $this->table;
    }

    /**
     * Global setting rows assigned to this group (group membership lives on the global row).
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'group_id')->global()->ordered();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}

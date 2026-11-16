<?php

namespace Ahs12\Setanjo\Tests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A scope model unlike User/Company: UUID key, no `name` column, soft deletes and a
 * global scope (only active branches), like a tenant or branch scope in an app.
 */
class Branch extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['title', 'code', 'is_active'];

    protected $table = 'branches';

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query) => $query->where('is_active', true));
    }
}

<?php

namespace Ahs12\Setanjo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $setting_key
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property string $action
 * @property string|null $old_value
 * @property string|null $new_value
 * @property array<string, mixed>|null $old_meta
 * @property array<string, mixed>|null $new_meta
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property Carbon $created_at
 */
class SettingHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'setanjo_history';

    protected $fillable = [
        'setting_key',
        'scope_type',
        'scope_id',
        'action',
        'old_value',
        'new_value',
        'old_meta',
        'new_meta',
        'causer_type',
        'causer_id',
    ];

    protected $casts = [
        'old_meta' => 'array',
        'new_meta' => 'array',
    ];

    public function getTable()
    {
        return config('setanjo.history.table') ?: $this->table;
    }

    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }

    public function causer(): MorphTo
    {
        return $this->morphTo('causer');
    }
}

<?php

namespace Ahs12\Setanjo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An explicit on/off state of a feature for one scope (user, branch, tenant, ...).
 *
 * @property int $id
 * @property int $feature_id
 * @property string $scope_type
 * @property int $scope_id
 * @property bool $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FeatureOverride extends Model
{
    protected $table = 'setanjo_feature_overrides';

    protected $fillable = [
        'feature_id',
        'scope_type',
        'scope_id',
        'enabled',
    ];

    protected $casts = [
        'feature_id' => 'integer',
        'enabled' => 'boolean',
    ];

    public function getTable()
    {
        return config('setanjo.features.overrides_table') ?: $this->table;
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }
}

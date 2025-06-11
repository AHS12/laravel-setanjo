<?php

namespace Ahs12\Setanjo\Models;

use Ahs12\Setanjo\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $key
 * @property mixed $value
 * @property string|null $description
 * @property SettingType $type
 * @property string|null $tenantable_type
 * @property int|null $tenantable_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
    ];

    protected $casts = [
        'value' => 'string',
        'type' => SettingType::class,
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

    public function getValueAttribute($value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Get the raw type value to avoid casting issues
        $typeValue = $this->getRawOriginal('type') ?? $this->attributes['type'] ?? null;

        if (! $typeValue) {
            return $value;
        }

        $type = SettingType::from($typeValue);

        return match ($type) {
            SettingType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::INTEGER => (int) $value,
            SettingType::FLOAT => (float) $value,
            SettingType::ARRAY , SettingType::JSON => json_decode($value, true),
            SettingType::OBJECT => json_decode($value),
            SettingType::STRING => $value,
        };
    }

    public function setValueAttribute($value): void
    {
        // Handle null values explicitly
        if ($value === null) {
            $this->attributes['value'] = null;

            return;
        }

        // Get the raw type value
        $typeValue = $this->getRawOriginal('type') ?? $this->attributes['type'] ?? null;

        if (! $typeValue) {
            $this->attributes['value'] = (string) $value;

            return;
        }

        $type = SettingType::from($typeValue);

        $this->attributes['value'] = match ($type) {
            SettingType::ARRAY , SettingType::JSON, SettingType::OBJECT => json_encode($value),
            SettingType::BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Automatically detect and set the type based on value
     */
    public function setTypeFromValue($value): void
    {
        $this->type = SettingType::fromValue($value);
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
}

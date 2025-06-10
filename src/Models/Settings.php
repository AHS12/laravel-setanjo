<?php

namespace Ahs12\Setanjo\Models;

use Ahs12\Setanjo\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('setanjo.table', 'settings'));
    }

    /**
     * Get the tenantable model (polymorphic relationship)
     */
    public function tenantable(): MorphTo
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        return $this->morphTo($column);
    }

    /**
     * Get the parsed value based on type
     */
    public function getValueAttribute($value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            SettingType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::INTEGER => (int) $value,
            SettingType::FLOAT => (float) $value,
            SettingType::ARRAY , SettingType::JSON => json_decode($value, true),
            SettingType::OBJECT => json_decode($value),
            SettingType::STRING => $value,
        };
    }

    /**
     * Set the value attribute with proper encoding
     */
    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = match ($this->type) {
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

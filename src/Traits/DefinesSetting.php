<?php

namespace Ahs12\Setanjo\Traits;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Enums\SettingType;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Sensible defaults for {@see SettingDefinition}.
 *
 * Designed for backed enums: the case value is the key. Override any method with a
 * `match ($this)` to customise individual cases.
 */
trait DefinesSetting
{
    public function key(): string
    {
        return $this instanceof BackedEnum ? (string) $this->value : static::class;
    }

    public function default(): mixed
    {
        return $this->isFeature() ? false : null;
    }

    public function type(): SettingType
    {
        if ($this->isFeature()) {
            return SettingType::BOOLEAN;
        }

        $default = $this->default();

        return $default === null ? SettingType::STRING : SettingType::fromValue($default);
    }

    public function label(): string
    {
        return Str::headline($this->key());
    }

    public function description(): ?string
    {
        return null;
    }

    public function group(): ?string
    {
        return null;
    }

    public function rules(): array|string
    {
        return [];
    }

    public function meta(): array
    {
        return [];
    }

    public function isFeature(): bool
    {
        return false;
    }

    public function scopes(): array
    {
        return [];
    }
}

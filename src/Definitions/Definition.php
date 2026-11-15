<?php

namespace Ahs12\Setanjo\Definitions;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Enums\SettingType;
use Illuminate\Support\Str;

/**
 * A definition built from an array (config file or `Setanjo::define()`).
 */
class Definition implements SettingDefinition
{
    /**
     * @param  array<int, mixed>|string  $rules
     * @param  array<string, mixed>  $meta
     * @param  array<int, class-string>  $scopes
     */
    public function __construct(
        protected string $key,
        protected mixed $default = null,
        protected ?SettingType $type = null,
        protected ?string $label = null,
        protected ?string $description = null,
        protected ?string $group = null,
        protected array|string $rules = [],
        protected array $meta = [],
        protected bool $feature = false,
        protected array $scopes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(string $key, array $attributes): self
    {
        $type = $attributes['type'] ?? null;

        return new self(
            key: $key,
            default: $attributes['default'] ?? $attributes['value'] ?? null,
            type: $type instanceof SettingType ? $type : ($type ? SettingType::from($type) : null),
            label: $attributes['label'] ?? null,
            description: $attributes['description'] ?? null,
            group: $attributes['group'] ?? null,
            rules: $attributes['rules'] ?? [],
            meta: $attributes['meta'] ?? [],
            feature: (bool) ($attributes['feature'] ?? false),
            scopes: $attributes['scopes'] ?? [],
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function default(): mixed
    {
        return $this->default ?? ($this->feature ? false : null);
    }

    public function type(): SettingType
    {
        if ($this->feature) {
            return SettingType::BOOLEAN;
        }

        if ($this->type !== null) {
            return $this->type;
        }

        return $this->default === null ? SettingType::STRING : SettingType::fromValue($this->default);
    }

    public function label(): string
    {
        return $this->label ?? Str::headline($this->key);
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function group(): ?string
    {
        return $this->group;
    }

    public function rules(): array|string
    {
        return $this->rules;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function isFeature(): bool
    {
        return $this->feature;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }
}

<?php

namespace Ahs12\Setanjo\Contracts;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Traits\DefinesSetting;

/**
 * Declares a setting (or feature flag) in code: its key, default, type and metadata.
 *
 * Values stay in the database; the definition supplies everything else. Implement it on a
 * backed enum together with the {@see DefinesSetting} trait and
 * override only what you need.
 */
interface SettingDefinition
{
    public function key(): string;

    public function default(): mixed;

    public function type(): SettingType;

    public function label(): string;

    public function description(): ?string;

    /**
     * Group slug or name this setting belongs to (null = ungrouped).
     */
    public function group(): ?string;

    /**
     * Laravel validation rules applied to the value before it is stored.
     *
     * @return array<int, mixed>|string
     */
    public function rules(): array|string;

    /**
     * Free-form metadata (unit, min, max, input, options, help, sensitive, tags, ...).
     *
     * @return array<string, mixed>
     */
    public function meta(): array;

    public function isFeature(): bool;

    /**
     * Scope model classes allowed to override this setting. Empty = any scope.
     *
     * @return array<int, class-string>
     */
    public function scopes(): array;
}

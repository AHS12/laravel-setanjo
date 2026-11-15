<?php

namespace Ahs12\Setanjo\Traits;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Features\PendingFeature;
use BackedEnum;

/**
 * Feature-flag helpers for a model that acts as a scope (user, branch, tenant, ...).
 */
trait HasFeatures
{
    public function feature(string|BackedEnum|SettingDefinition $key): PendingFeature
    {
        return app('setanjo')->feature($key)->for($this);
    }

    public function hasFeature(string|BackedEnum|SettingDefinition $key): bool
    {
        return $this->feature($key)->enabled();
    }

    public function enableFeature(string|BackedEnum|SettingDefinition $key): static
    {
        $this->feature($key)->enable();

        return $this;
    }

    public function disableFeature(string|BackedEnum|SettingDefinition $key): static
    {
        $this->feature($key)->disable();

        return $this;
    }

    /**
     * Remove this model's override so the flag inherits from the next scope up.
     */
    public function inheritFeature(string|BackedEnum|SettingDefinition $key): static
    {
        $this->feature($key)->inherit();

        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function featureStates(): array
    {
        return app('setanjo')->features()->for($this)->all();
    }
}

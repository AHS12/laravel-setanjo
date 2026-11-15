<?php

namespace Ahs12\Setanjo\Definitions;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use BackedEnum;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnitEnum;

/**
 * Holds every declared setting / feature flag, keyed by setting key.
 *
 * Definitions listed in `config('setanjo.definitions')` are loaded lazily on first access.
 */
class DefinitionRegistry
{
    /** @var array<string, SettingDefinition> */
    protected array $definitions = [];

    protected bool $configLoaded = false;

    /**
     * Register definitions.
     *
     * Accepts: an enum class-string whose cases implement SettingDefinition, a
     * SettingDefinition instance, a class-string of one, or a list of any of these.
     */
    public function register(mixed ...$definitions): static
    {
        foreach ($definitions as $definition) {
            foreach ($this->normalize($definition) as $resolved) {
                $this->definitions[$resolved->key()] = $resolved;
            }
        }

        return $this;
    }

    /**
     * Define a single setting from attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function define(string $key, array $attributes = []): SettingDefinition
    {
        $definition = Definition::fromArray($key, $attributes);
        $this->definitions[$key] = $definition;

        return $definition;
    }

    public function get(string $key): ?SettingDefinition
    {
        $this->loadFromConfig();

        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        $this->loadFromConfig();

        return $this->definitions;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function features(): array
    {
        return array_filter($this->all(), fn (SettingDefinition $definition) => $definition->isFeature());
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function settings(): array
    {
        return array_filter($this->all(), fn (SettingDefinition $definition) => ! $definition->isFeature());
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Forget every registered definition (mainly for tests).
     */
    public function clear(): static
    {
        $this->definitions = [];
        $this->configLoaded = true;

        return $this;
    }

    /**
     * Slug for a definition's group, or null when ungrouped.
     */
    public static function groupSlug(?SettingDefinition $definition): ?string
    {
        $group = $definition?->group();

        return $group === null || $group === '' ? null : Str::slug($group);
    }

    /**
     * Human-readable name for a definition's group.
     */
    public static function groupName(?SettingDefinition $definition): ?string
    {
        $group = $definition?->group();

        if ($group === null || $group === '') {
            return null;
        }

        return $group === Str::slug($group) ? Str::headline($group) : $group;
    }

    protected function loadFromConfig(): void
    {
        if ($this->configLoaded) {
            return;
        }

        $this->configLoaded = true;
        $loaded = $this->definitions;
        $this->definitions = [];

        foreach (config('setanjo.definitions', []) as $key => $definition) {
            if (is_string($key) && is_array($definition)) {
                $this->define($key, $definition);

                continue;
            }

            $this->register($definition);
        }

        // Runtime registrations win over config.
        $this->definitions = array_merge($this->definitions, $loaded);
    }

    /**
     * @return array<int, SettingDefinition>
     */
    protected function normalize(mixed $definition): array
    {
        if ($definition instanceof SettingDefinition) {
            return [$definition];
        }

        if (is_array($definition)) {
            return array_merge(...array_map(fn ($item) => $this->normalize($item), array_values($definition)) ?: [[]]);
        }

        if (is_string($definition) && enum_exists($definition)) {
            /** @var class-string<UnitEnum> $definition */
            $cases = $definition::cases();

            foreach ($cases as $case) {
                if (! $case instanceof SettingDefinition) {
                    throw new InvalidArgumentException(
                        "Enum [{$definition}] must implement ".SettingDefinition::class.'.'
                    );
                }
            }

            return $cases;
        }

        if (is_string($definition) && class_exists($definition)) {
            $instance = app($definition);

            if ($instance instanceof SettingDefinition) {
                return [$instance];
            }
        }

        $given = is_string($definition) ? $definition : get_debug_type($definition);

        throw new InvalidArgumentException("Cannot register setting definition [{$given}].");
    }

    /**
     * Resolve a key from a string, a definition, or a backed enum case.
     */
    public static function keyOf(string|SettingDefinition|BackedEnum $key): string
    {
        return match (true) {
            $key instanceof SettingDefinition => $key->key(),
            $key instanceof BackedEnum => (string) $key->value,
            default => $key,
        };
    }
}

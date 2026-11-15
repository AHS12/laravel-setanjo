<?php

namespace Ahs12\Setanjo\Features;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\ValueObjects\ResolvedValue;
use Illuminate\Database\Eloquent\Model;

/**
 * Every defined feature flag, resolved for one subject.
 */
class PendingFeatures
{
    protected ?Model $subject = null;

    public function __construct(protected SetanjoManager $manager) {}

    public function for(?Model $subject): self
    {
        $instance = clone $this;
        $instance->subject = $subject;

        return $instance;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return array_map(fn (ResolvedValue $resolved) => (bool) $resolved->value, $this->explain());
    }

    /**
     * @return array<int, string>
     */
    public function enabled(): array
    {
        return array_keys(array_filter($this->all()));
    }

    /**
     * Every feature key: defined in code first, then flags that only exist in the database.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_values(array_unique([
            ...array_map(fn (SettingDefinition $definition) => $definition->key(), array_values($this->manager->definitions()->features())),
            ...array_keys($this->manager->featureRepository()->flags()),
        ]));
    }

    /**
     * @return array<string, ResolvedValue>
     */
    public function explain(): array
    {
        $subject = $this->subject ?? $this->manager->subject();
        $resolved = [];

        foreach ($this->keys() as $key) {
            $resolved[$key] = $this->manager->resolveFeature($key, $subject);
        }

        return $resolved;
    }
}

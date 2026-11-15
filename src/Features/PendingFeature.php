<?php

namespace Ahs12\Setanjo\Features;

use Ahs12\Setanjo\Contracts\FeatureRepositoryInterface;
use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Events\FeatureToggled;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Exceptions\UndefinedFeatureException;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\ValueObjects\ResolvedValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Fluent access to one feature flag.
 *
 * Flags are stored in their own tables: the global state on the flag row, explicit
 * states per scope in the overrides table. Reads walk the scope chain (scope, ...,
 * global, default). Writes target the subject given to for(), or global when none was given.
 */
class PendingFeature
{
    protected ?Model $subject = null;

    public function __construct(
        protected SetanjoManager $manager,
        protected string $key,
    ) {
        $this->ensureDefined();
    }

    public function for(?Model $subject): self
    {
        $instance = clone $this;
        $instance->subject = $subject;

        return $instance;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function enabled(): bool
    {
        return (bool) $this->explain()->value;
    }

    public function disabled(): bool
    {
        return ! $this->enabled();
    }

    public function explain(): ResolvedValue
    {
        return $this->manager->resolveFeature($this->key, $this->subject ?? $this->manager->subject());
    }

    /**
     * The explicit state stored for the target scope: true/false, or null when it inherits.
     */
    public function override(): ?bool
    {
        $scope = $this->target();

        if ($scope === null) {
            return $this->repository()->flag($this->key)['enabled'] ?? null;
        }

        return $this->repository()->overrides($scope)[$this->key] ?? null;
    }

    public function enable(): self
    {
        return $this->write(true);
    }

    public function disable(): self
    {
        return $this->write(false);
    }

    /**
     * Set an explicit state, or null to inherit.
     */
    public function toggle(?bool $state): self
    {
        return $state === null ? $this->inherit() : $this->write($state);
    }

    /**
     * Remove the explicit state for the target scope so it inherits from the next one up.
     *
     * For global this clears the stored state so the definition default applies.
     */
    public function inherit(): self
    {
        $scope = $this->target();

        if ($scope === null) {
            if (($this->repository()->flag($this->key)['enabled'] ?? null) === null) {
                return $this;
            }

            $this->repository()->setGlobal($this->key, null);
        } elseif (! $this->repository()->forgetOverride($this->key, $scope)) {
            return $this;
        }

        event(new FeatureToggled($this->key, $scope, null));

        return $this;
    }

    /**
     * Effective meta: definition meta merged with the meta stored on the flag row.
     */
    public function meta(?string $item = null, mixed $default = null): mixed
    {
        $meta = array_replace(
            $this->definition()?->meta() ?? [],
            $this->repository()->flag($this->key)['meta'] ?? [],
        );

        return $item === null ? $meta : Arr::get($meta, $item, $default);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function setMeta(array $meta): self
    {
        $stored = $this->repository()->flag($this->key)['meta'] ?? [];

        return $this->replaceMeta(array_replace($stored, $meta));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function replaceMeta(?array $meta): self
    {
        if ($meta !== null) {
            $bytes = strlen(json_encode($meta, JSON_THROW_ON_ERROR));
            $max = (int) config('setanjo.meta.max_bytes', 16384);

            if ($max > 0 && $bytes > $max) {
                throw InvalidSettingValueException::metaTooLarge($this->key, $bytes, $max);
            }
        }

        $this->repository()->updateAttributes(
            $this->key,
            ['meta' => $meta === [] ? null : $meta],
            $this->manager->featureAttributes($this->definition()),
        );

        return $this;
    }

    /**
     * Everything known about the flag, resolved for the subject.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $definition = $this->definition();
        $flag = $this->repository()->flag($this->key);
        $resolved = $this->explain();

        return [
            'key' => $this->key,
            'defined' => $definition !== null,
            'label' => $flag['name'] ?? $definition?->label() ?? $this->key,
            'description' => $flag['description'] ?? $definition?->description(),
            'group' => DefinitionRegistry::groupSlug($definition),
            'group_id' => $flag['group_id'] ?? null,
            'default' => (bool) ($definition?->default() ?? false),
            'global' => $flag['enabled'] ?? null,
            'enabled' => (bool) $resolved->value,
            'source' => $resolved->source->value,
            'scope_type' => $resolved->scope ? $resolved->scope::class : null,
            'scope_id' => $resolved->scope?->getKey(),
            'override_count' => $this->repository()->overrideCounts()[$this->key] ?? 0,
            'meta' => $this->meta(),
        ];
    }

    protected function write(bool $state): self
    {
        $scope = $this->target();
        $attributes = $this->manager->featureAttributes($this->definition());

        if ($scope === null) {
            $this->repository()->setGlobal($this->key, $state, $attributes);
        } else {
            $this->manager->for($scope); // validates the tenant type
            $this->manager->assertScopeAllowed($this->key, $scope);
            $this->repository()->setOverride($this->key, $scope, $state, $attributes);
        }

        event(new FeatureToggled($this->key, $scope, $state));

        return $this;
    }

    /**
     * The scope written to: the explicit subject, else the manager's scope, else global.
     */
    protected function target(): ?Model
    {
        return $this->subject ?? $this->manager->tenant();
    }

    protected function repository(): FeatureRepositoryInterface
    {
        return $this->manager->featureRepository();
    }

    protected function definition(): ?SettingDefinition
    {
        return $this->manager->definitions()->get($this->key);
    }

    protected function ensureDefined(): void
    {
        if (! config('setanjo.features.strict', false)) {
            return;
        }

        $definition = $this->definition();

        if ($definition === null || ! $definition->isFeature()) {
            throw UndefinedFeatureException::forKey($this->key);
        }
    }
}

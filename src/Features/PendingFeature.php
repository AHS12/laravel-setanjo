<?php

namespace Ahs12\Setanjo\Features;

use Ahs12\Setanjo\Contracts\FeatureRepositoryInterface;
use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Events\FeatureRulesChanged;
use Ahs12\Setanjo\Events\FeatureToggled;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Exceptions\UndefinedFeatureException;
use Ahs12\Setanjo\SetanjoManager;
use Ahs12\Setanjo\ValueObjects\FeatureRules;
use Ahs12\Setanjo\ValueObjects\ResolvedValue;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

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
        $previous = $this->override();

        if ($scope === null) {
            if ($previous === null) {
                return $this;
            }

            $this->repository()->setGlobal($this->key, null);
        } elseif (! $this->repository()->forgetOverride($this->key, $scope)) {
            return $this;
        }

        event(new FeatureToggled($this->key, $scope, null, $previous === null ? null : (bool) $previous));

        return $this;
    }

    /**
     * The flag's rules (schedule, rollout, required flags, archive). They belong to the flag, not to
     * a scope: for() does not change what they apply to.
     */
    public function rules(): FeatureRules
    {
        return FeatureRules::fromArray($this->repository()->flag($this->key));
    }

    /**
     * Keep the flag off before $from and from $until on (either may be null for an open end).
     */
    public function schedule(DateTimeInterface|string|null $from, DateTimeInterface|string|null $until = null): self
    {
        return $this->updateRules(['enabled_from' => $from, 'enabled_until' => $until]);
    }

    /**
     * Give the flag to a share of records (0-100); null removes the limit.
     */
    public function rollout(?int $percent): self
    {
        return $this->updateRules(['rollout' => $percent]);
    }

    /**
     * Flags that must be on (for the same subject) for this one to be on; null or [] removes them.
     *
     * @param  array<int, string>|string|null  $keys
     */
    public function requires(array|string|null $keys): self
    {
        return $this->updateRules(['requires' => $keys === null ? [] : (array) $keys]);
    }

    /**
     * Hide the flag from the lists. An archived flag always reads as off; overrides and history stay.
     */
    public function archive(): self
    {
        return $this->archived() ? $this : $this->updateRules(['archived_at' => now()]);
    }

    public function restore(): self
    {
        return $this->updateRules(['archived_at' => null]);
    }

    public function archived(): bool
    {
        return $this->rules()->isArchived();
    }

    /**
     * Change some rules at once. Keys: enabled_from, enabled_until, rollout, requires, archived_at.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws InvalidSettingValueException
     */
    public function updateRules(array $changes): self
    {
        $changes = array_intersect_key($changes, array_flip(['enabled_from', 'enabled_until', 'rollout', 'requires', 'archived_at']));

        if (array_key_exists('requires', $changes)) {
            $changes['requires'] = collect((array) $changes['requires'])
                ->filter(fn ($required) => is_scalar($required) && trim((string) $required) !== '')
                ->map(fn ($required) => trim((string) $required))
                ->unique()
                ->values()
                ->all();
        }

        $old = $this->rules();

        try {
            $new = $old->with($changes);
        } catch (Throwable) {
            throw InvalidSettingValueException::invalidRule($this->key, 'enabled_from', 'The schedule must use valid dates.');
        }

        $this->validateRules($new);

        if ($new->toArray() === $old->toArray()) {
            return $this;
        }

        $this->repository()->updateAttributes($this->key, $new->toAttributes(), $this->manager->featureAttributes($this->definition()));

        event(new FeatureRulesChanged($this->key, $old, $new));

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
            'rules' => $this->rules()->toArray(),
        ];
    }

    /**
     * @throws InvalidSettingValueException
     */
    protected function validateRules(FeatureRules $rules): void
    {
        if ($rules->rollout !== null && ($rules->rollout < 0 || $rules->rollout > 100)) {
            throw InvalidSettingValueException::invalidRule($this->key, 'rollout', 'The rollout must be between 0 and 100.');
        }

        if ($rules->enabledFrom !== null && $rules->enabledUntil !== null && $rules->enabledUntil->lte($rules->enabledFrom)) {
            throw InvalidSettingValueException::invalidRule($this->key, 'enabled_until', 'The end of the schedule must be after its start.');
        }

        foreach ($rules->requires as $required) {
            if ($required === $this->key) {
                throw InvalidSettingValueException::invalidRule($this->key, 'requires', 'A flag cannot require itself.');
            }

            if ($this->manager->definitions()->get($required)?->isFeature() !== true && $this->repository()->flag($required) === null) {
                throw InvalidSettingValueException::invalidRule($this->key, 'requires', "There is no feature flag [{$required}].");
            }

            if ($this->leadsTo($required, $this->key)) {
                throw InvalidSettingValueException::invalidRule($this->key, 'requires', "[{$required}] already requires [{$this->key}], directly or through other flags.");
            }
        }
    }

    /**
     * Whether $from requires $target through stored `requires` rules.
     *
     * @param  array<string, true>  $seen
     */
    protected function leadsTo(string $from, string $target, array $seen = []): bool
    {
        if ($from === $target) {
            return true;
        }

        $seen[$from] = true;

        foreach ($this->repository()->flag($from)['requires'] ?? [] as $next) {
            if (! isset($seen[$next]) && $this->leadsTo((string) $next, $target, $seen)) {
                return true;
            }
        }

        return false;
    }

    protected function write(bool $state): self
    {
        $scope = $this->target();
        $attributes = $this->manager->featureAttributes($this->definition());
        $previous = $this->override();

        if ($scope === null) {
            $this->repository()->setGlobal($this->key, $state, $attributes);
        } else {
            $this->manager->for($scope); // validates the tenant type
            $this->manager->assertScopeAllowed($this->key, $scope);
            $this->repository()->setOverride($this->key, $scope, $state, $attributes);
        }

        event(new FeatureToggled($this->key, $scope, $state, $previous === null ? null : (bool) $previous));

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

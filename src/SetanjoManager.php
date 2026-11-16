<?php

namespace Ahs12\Setanjo;

use Ahs12\Setanjo\Contracts\FeatureRepositoryInterface;
use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Enums\ValueSource;
use Ahs12\Setanjo\Events\SettingForgotten;
use Ahs12\Setanjo\Events\SettingMetaUpdated;
use Ahs12\Setanjo\Events\SettingsFlushed;
use Ahs12\Setanjo\Events\SettingUpdated;
use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Ahs12\Setanjo\Exceptions\InvalidTenantException;
use Ahs12\Setanjo\Features\PendingFeature;
use Ahs12\Setanjo\Features\PendingFeatures;
use Ahs12\Setanjo\Groups\GroupManager;
use Ahs12\Setanjo\Support\ScopeResolver;
use Ahs12\Setanjo\ValueObjects\FeatureRules;
use Ahs12\Setanjo\ValueObjects\ResolvedValue;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class SetanjoManager
{
    protected $currentTenant = null;

    protected DefinitionRegistry $definitions;

    protected ScopeResolver $scopes;

    protected FeatureRepositoryInterface $features;

    public function __construct(
        protected SettingsRepositoryInterface $repository,
        ?DefinitionRegistry $definitions = null,
        ?ScopeResolver $scopes = null,
        ?FeatureRepositoryInterface $features = null,
    ) {
        $this->definitions = $definitions ?? app(DefinitionRegistry::class);
        $this->scopes = $scopes ?? app(ScopeResolver::class);
        $this->features = $features ?? app(FeatureRepositoryInterface::class);
    }

    /**
     * Set the current tenant for subsequent operations
     */
    public function for($tenant): self
    {
        $this->validateTenant($tenant);

        $instance = clone $this;
        $instance->currentTenant = $tenant;

        return $instance;
    }

    /**
     * Set tenant by ID and optionally model class (for polymorphic mode)
     */
    public function forTenantId(int $tenantId, ?string $modelClass = null): self
    {
        $modelClass = $this->validateAndGetModelClass($modelClass);

        $tenant = $modelClass::find($tenantId);

        if (! $tenant) {
            throw InvalidTenantException::notFound($modelClass, $tenantId);
        }

        return $this->for($tenant);
    }

    /**
     * The scope this manager reads and writes (null = global).
     */
    public function tenant(): ?Model
    {
        return $this->currentTenant;
    }

    /**
     * Get the value stored for the current scope.
     *
     * Falls back to $default, then to the definition default. Does not walk the scope
     * chain; use {@see effective()} for that.
     */
    public function get(string|BackedEnum|SettingDefinition $key, $default = null)
    {
        $key = DefinitionRegistry::keyOf($key);
        $record = $this->repository->record($key, $this->currentTenant);

        if ($record !== null) {
            return $record['value'];
        }

        return $default ?? $this->definitions->get($key)?->default();
    }

    /**
     * Set a setting value for the current scope.
     *
     * @param  array<string, mixed>|null  $meta  merged into the key's meta when given
     */
    public function set(string|BackedEnum|SettingDefinition $key, $value, ?array $meta = null): self
    {
        $key = DefinitionRegistry::keyOf($key);
        $definition = $this->definitions->get($key);
        $type = null;

        if ($definition?->isFeature()) {
            throw InvalidSettingValueException::isFeature($key);
        }

        if ($definition !== null) {
            $this->ensureScopeAllowed($definition, $this->currentTenant);
            $value = $this->prepareValue($definition, $value);
            $type = $definition->type();
        }

        $previous = $this->repository->record($key, $this->currentTenant);

        $this->repository->set($key, $value, $this->currentTenant, $type);

        if ($meta !== null) {
            $this->setMeta($key, $meta);
        }

        event(new SettingUpdated($key, $this->currentTenant, $previous['value'] ?? null, $value, $previous === null));

        return $this;
    }

    /**
     * Check if a setting is stored for the current scope (a stored null counts).
     */
    public function has(string|BackedEnum|SettingDefinition $key): bool
    {
        return $this->repository->has(DefinitionRegistry::keyOf($key), $this->currentTenant);
    }

    /**
     * Remove a setting for the current scope
     */
    public function forget(string|BackedEnum|SettingDefinition $key): self
    {
        $key = DefinitionRegistry::keyOf($key);
        $previous = $this->repository->record($key, $this->currentTenant);

        $this->repository->forget($key, $this->currentTenant);

        if ($previous !== null) {
            event(new SettingForgotten($key, $this->currentTenant, $previous['value']));
        }

        return $this;
    }

    /**
     * Get all settings for current tenant
     */
    public function all()
    {
        return $this->repository->all($this->currentTenant);
    }

    /**
     * Remove all settings for current tenant
     */
    public function flush(): self
    {
        $this->repository->flush($this->currentTenant);

        event(new SettingsFlushed($this->currentTenant));

        return $this;
    }

    /**
     * The effective value: first scope in the chain that has a value, then global, then default.
     */
    public function effective(string|BackedEnum|SettingDefinition $key, $default = null): mixed
    {
        $resolved = $this->explain($key);

        if ($resolved->source === ValueSource::DEFAULT && $resolved->value === null) {
            return $default;
        }

        return $resolved->value;
    }

    /**
     * Resolve a key for this manager's subject and report where the value came from.
     */
    public function explain(string|BackedEnum|SettingDefinition $key): ResolvedValue
    {
        return $this->resolve(DefinitionRegistry::keyOf($key), $this->subject());
    }

    /**
     * Resolve a key for an explicit subject through the configured scope chain.
     */
    public function resolve(string $key, ?Model $subject): ResolvedValue
    {
        $definition = $this->definitions->get($key);
        $chain = $this->scopes->chain($subject, $definition?->scopes() ?? []);

        $this->repository->loadMany([...$chain, null]);

        foreach ($chain as $scope) {
            $record = $this->repository->record($key, $scope);

            if ($record !== null) {
                return new ResolvedValue($key, $record['value'], ValueSource::SCOPE, $scope);
            }
        }

        $global = $this->repository->record($key, null);

        if ($global !== null) {
            return new ResolvedValue($key, $global['value'], ValueSource::GLOBAL);
        }

        return new ResolvedValue($key, $definition?->default(), ValueSource::DEFAULT);
    }

    /**
     * The subject used for resolution: the current scope, or the default subject (auth user).
     */
    public function subject(): ?Model
    {
        return $this->currentTenant ?? $this->scopes->defaultSubject();
    }

    /**
     * Effective meta for a key: definition meta merged with the meta stored on the global row.
     *
     * Meta belongs to the key, not to a scope, so this ignores the current tenant.
     */
    public function meta(string|BackedEnum|SettingDefinition $key, ?string $item = null, mixed $default = null): mixed
    {
        $key = DefinitionRegistry::keyOf($key);
        $meta = array_replace(
            $this->definitions->get($key)?->meta() ?? [],
            $this->repository->record($key, null)['meta'] ?? [],
        );

        return $item === null ? $meta : Arr::get($meta, $item, $default);
    }

    /**
     * Merge items into the meta stored for a key.
     *
     * @param  array<string, mixed>  $meta
     */
    public function setMeta(string|BackedEnum|SettingDefinition $key, array $meta): self
    {
        $key = DefinitionRegistry::keyOf($key);
        $stored = $this->repository->record($key, null)['meta'] ?? [];

        return $this->writeMeta($key, array_replace($stored, $meta));
    }

    /**
     * Replace the meta stored for a key (null clears it; definition meta still applies).
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function replaceMeta(string|BackedEnum|SettingDefinition $key, ?array $meta): self
    {
        return $this->writeMeta(DefinitionRegistry::keyOf($key), $meta === [] ? null : $meta);
    }

    /**
     * Everything known about a key, resolved for this manager's subject.
     *
     * @return array<string, mixed>
     */
    public function describe(string|BackedEnum|SettingDefinition $key): array
    {
        $key = DefinitionRegistry::keyOf($key);
        $definition = $this->definitions->get($key);
        $global = $this->repository->record($key, null);
        $resolved = $this->explain($key);

        return [
            'key' => $key,
            'defined' => $definition !== null,
            'label' => $definition?->label() ?? $key,
            'description' => $definition?->description() ?? $global['description'] ?? null,
            'type' => $definition?->type()->value ?? $global['type'] ?? null,
            'group' => DefinitionRegistry::groupSlug($definition),
            'group_id' => $global['group_id'] ?? null,
            'is_feature' => (bool) $definition?->isFeature(),
            'is_locked' => (bool) ($global['is_locked'] ?? false),
            'default' => $definition?->default(),
            'value' => $resolved->value,
            'source' => $resolved->source->value,
            'scope_type' => $resolved->scope ? $resolved->scope::class : null,
            'scope_id' => $resolved->scope?->getKey(),
            'meta' => $this->meta($key),
        ];
    }

    public function definitions(): DefinitionRegistry
    {
        return $this->definitions;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function define(string $key, array $attributes = []): SettingDefinition
    {
        return $this->definitions->define($key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function defineFeature(string $key, array $attributes = []): SettingDefinition
    {
        return $this->definitions->define($key, ['feature' => true, ...$attributes]);
    }

    public function feature(string|BackedEnum|SettingDefinition $key): PendingFeature
    {
        return new PendingFeature($this, DefinitionRegistry::keyOf($key));
    }

    public function features(): PendingFeatures
    {
        return new PendingFeatures($this);
    }

    public function groups(): GroupManager
    {
        return new GroupManager($this, $this->repository);
    }

    /**
     * @param  Closure(Model|null): iterable<Model|null>|null  $resolver
     */
    public function resolveScopesUsing(?Closure $resolver): self
    {
        $this->scopes->resolveScopesUsing($resolver);

        return $this;
    }

    /**
     * @param  Closure(): (Model|null)|null  $resolver
     */
    public function resolveDefaultSubjectUsing(?Closure $resolver): self
    {
        $this->scopes->resolveDefaultSubjectUsing($resolver);

        return $this;
    }

    public function scopeResolver(): ScopeResolver
    {
        return $this->scopes;
    }

    /**
     * Forget the cached payload of one scope (default: the current scope).
     */
    public function forgetCache(?Model $scope = null): self
    {
        $scope ??= $this->currentTenant;

        $this->repository->forgetCache($scope);
        $this->repository->reset();
        $this->features->forgetCache($scope);

        return $this;
    }

    /**
     * Invalidate every cached scope (settings and feature flags).
     */
    public function flushCache(): self
    {
        $this->repository->flushCache();
        $this->features->reset();

        return $this;
    }

    public function featureRepository(): FeatureRepositoryInterface
    {
        return $this->features;
    }

    /**
     * Resolve a feature flag for a subject: archive, closest scope override, global state or default, then the rules.
     */
    public function resolveFeature(string $key, ?Model $subject): ResolvedValue
    {
        return $this->resolveFeatureFor($key, $subject, []);
    }

    /**
     * Archived → off. A per-record override wins over every rule. Otherwise the global state (or
     * the default) is the switch: when it is on, the schedule, the required flags and the rollout
     * may each still turn it off for this subject.
     *
     * @param  array<string, true>  $visiting  flags being resolved up the `requires` path (cycle guard)
     */
    protected function resolveFeatureFor(string $key, ?Model $subject, array $visiting): ResolvedValue
    {
        $definition = $this->definitions->get($key);
        $flag = $this->features->flag($key);
        $rules = FeatureRules::fromArray($flag);

        if ($rules->isArchived()) {
            return new ResolvedValue($key, false, ValueSource::ARCHIVED);
        }

        $chain = $this->scopes->chain($subject, $definition?->scopes() ?? []);

        $this->features->loadOverrides($chain);

        foreach ($chain as $scope) {
            $overrides = $this->features->overrides($scope);

            if (array_key_exists($key, $overrides)) {
                return new ResolvedValue($key, $overrides[$key], ValueSource::SCOPE, $scope);
            }
        }

        $global = $flag['enabled'] ?? null;
        $base = $global === null
            ? new ResolvedValue($key, (bool) ($definition?->default() ?? false), ValueSource::DEFAULT)
            : new ResolvedValue($key, (bool) $global, ValueSource::GLOBAL);

        if ($base->value === false) {
            return $base;
        }

        if (! $rules->inWindow()) {
            return new ResolvedValue($key, false, ValueSource::SCHEDULE);
        }

        $visiting[$key] = true;

        foreach ($rules->requires as $required) {
            // A cycle cannot be satisfied: treat the flag that closes it as off.
            if (isset($visiting[$required]) || ! $this->resolveFeatureFor($required, $subject, $visiting)->value) {
                return new ResolvedValue($key, false, ValueSource::DEPENDENCY, detail: $required);
            }
        }

        if ($rules->limitsRollout()) {
            $bucket = $subject === null ? null : self::rolloutBucket($key, $subject);

            // Without a subject there is no one to place in a bucket: only a 100% rollout is on.
            return new ResolvedValue($key, $bucket !== null && $bucket < $rules->rollout, ValueSource::ROLLOUT, detail: $bucket);
        }

        return $base;
    }

    /**
     * A record's stable place (0-99) in a flag's rollout. The flag key is part of the hash, so
     * each flag picks a different slice of records; raising the percentage only adds records.
     */
    public static function rolloutBucket(string $key, Model $subject): int
    {
        return crc32($key.'|'.$subject->getMorphClass().':'.$subject->getKey()) % 100;
    }

    /**
     * Attributes used when a feature row has to be created for a key that only exists in code.
     *
     * @return array<string, mixed>
     */
    public function featureAttributes(?SettingDefinition $definition): array
    {
        return $definition === null ? [] : [
            'name' => $definition->label(),
            'description' => $definition->description(),
        ];
    }

    /**
     * @throws InvalidSettingValueException when the scope may not override the key
     */
    public function assertScopeAllowed(string $key, ?Model $scope): void
    {
        $definition = $this->definitions->get($key);

        if ($definition !== null) {
            $this->ensureScopeAllowed($definition, $scope);
        }
    }

    public function repository(): SettingsRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Validate and coerce a value against its definition.
     *
     * @throws InvalidSettingValueException
     */
    public function prepareValue(SettingDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $key = $definition->key();

        if (config('setanjo.validation.values', true)) {
            $rules = $this->valueRules($definition);

            if ($rules !== []) {
                $validator = Validator::make(['value' => $value], ['value' => $rules]);

                if ($validator->fails()) {
                    throw InvalidSettingValueException::failedValidation($key, $validator->errors()->toArray());
                }
            }
        }

        return $definition->type()->coerce($value, $key);
    }

    /**
     * @return array<int, mixed>
     */
    protected function valueRules(SettingDefinition $definition): array
    {
        $rules = $definition->rules();
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $meta = $this->meta($definition);
        $type = $definition->type();

        if (in_array($type, [SettingType::INTEGER, SettingType::FLOAT], true)) {
            if (isset($meta['min']) || isset($meta['max'])) {
                $rules[] = 'numeric';
            }
            if (isset($meta['min']) && is_numeric($meta['min'])) {
                $rules[] = 'min:'.$meta['min'];
            }
            if (isset($meta['max']) && is_numeric($meta['max'])) {
                $rules[] = 'max:'.$meta['max'];
            }
        }

        if (! empty($meta['options']) && is_array($meta['options']) && ! in_array($type, [SettingType::ARRAY, SettingType::JSON, SettingType::OBJECT], true)) {
            $allowed = array_is_list($meta['options']) ? $meta['options'] : array_keys($meta['options']);
            $rules[] = 'in:'.implode(',', array_map('strval', $allowed));
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function writeMeta(string $key, ?array $meta): self
    {
        if ($meta !== null) {
            $bytes = strlen(json_encode($meta, JSON_THROW_ON_ERROR));
            $max = (int) config('setanjo.meta.max_bytes', 16384);

            if ($max > 0 && $bytes > $max) {
                throw InvalidSettingValueException::metaTooLarge($key, $bytes, $max);
            }
        }

        $definition = $this->definitions->get($key);
        $old = $this->repository->record($key, null)['meta'] ?? null;

        $this->repository->updateAttributes($key, ['meta' => $meta], null, $this->createWith($definition));

        event(new SettingMetaUpdated($key, null, $old, $meta));

        return $this;
    }

    /**
     * Value + type used when a row has to be created for a key that only exists in code.
     *
     * @return array{value: mixed, type: SettingType}
     */
    public function createWith(?SettingDefinition $definition): array
    {
        return [
            'value' => $definition?->default(),
            'type' => $definition?->type() ?? SettingType::STRING,
        ];
    }

    protected function ensureScopeAllowed(SettingDefinition $definition, ?Model $scope): void
    {
        $allowed = $definition->scopes();

        if ($scope === null || $allowed === [] || $this->scopes->isAllowed($scope, $allowed)) {
            return;
        }

        throw InvalidSettingValueException::scopeNotAllowed($definition->key(), $scope::class);
    }

    /**
     * Validate tenant model
     */
    protected function validateTenant($tenant): void
    {
        if (! config('setanjo.validation.enabled', true) || $tenant === null) {
            return;
        }

        $this->validateTenantType($tenant);
    }

    /**
     * Common validation method for tenant configuration and types
     */
    protected function validateAndGetModelClass(?string $modelClass = null): string
    {
        if (! config('setanjo.validation.enabled', true)) {
            return $modelClass ?? throw InvalidTenantException::missingModelClassPolymorphic();
        }

        $tenancyMode = config('setanjo.tenancy_mode');

        if ($tenancyMode === 'strict') {
            $allowedClass = config('setanjo.strict_tenant_model');

            if (! $allowedClass) {
                throw InvalidTenantException::missingConfiguration('setanjo.strict_tenant_model');
            }

            return $allowedClass;
        }

        if ($tenancyMode === 'polymorphic') {
            if (! $modelClass) {
                throw InvalidTenantException::missingModelClassPolymorphic();
            }

            $this->validateModelClassInAllowedList($modelClass);

            return $modelClass;
        }

        throw InvalidTenantException::missingConfiguration('setanjo.tenancy_mode');
    }

    /**
     * Validate tenant instance against configuration
     */
    protected function validateTenantType($tenant): void
    {
        $tenancyMode = config('setanjo.tenancy_mode');

        if ($tenancyMode === 'strict') {
            $this->validateStrictMode($tenant);
        } elseif ($tenancyMode === 'polymorphic') {
            $this->validatePolymorphicMode($tenant);
        }
    }

    /**
     * Validate tenant for strict mode
     */
    protected function validateStrictMode($tenant): void
    {
        $allowedClass = config('setanjo.strict_tenant_model');

        if (! $allowedClass) {
            throw InvalidTenantException::missingConfiguration('setanjo.strict_tenant_model');
        }

        if (! ($tenant instanceof $allowedClass)) {
            throw InvalidTenantException::forStrictMode($tenant, $allowedClass);
        }
    }

    /**
     * Validate tenant for polymorphic mode
     */
    protected function validatePolymorphicMode($tenant): void
    {
        $allowedClasses = config('setanjo.allowed_tenant_models', []);

        if (! empty($allowedClasses)) {
            $tenantClass = get_class($tenant);
            $this->validateModelClassInAllowedList($tenantClass);
        }
    }

    /**
     * Validate model class is in allowed list
     */
    protected function validateModelClassInAllowedList(string $modelClass): void
    {
        $allowedClasses = config('setanjo.allowed_tenant_models', []);

        if (! empty($allowedClasses) && ! in_array($modelClass, $allowedClasses)) {
            throw InvalidTenantException::forPolymorphicMode(
                new $modelClass,
                $allowedClasses
            );
        }
    }
}

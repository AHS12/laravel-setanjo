<?php

namespace Ahs12\Setanjo;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Exceptions\InvalidTenantException;

class SetanjoManager
{
    protected $currentTenant = null;

    public function __construct(protected SettingsRepositoryInterface $repository) {}

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
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        return $this->repository->get($key, $default, $this->currentTenant);
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value): self
    {
        $this->repository->set($key, $value, $this->currentTenant);

        return $this;
    }

    /**
     * Check if a setting exists
     */
    public function has(string $key): bool
    {
        return $this->repository->has($key, $this->currentTenant);
    }

    /**
     * Remove a setting
     */
    public function forget(string $key): self
    {
        $this->repository->forget($key, $this->currentTenant);

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

        return $this;
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

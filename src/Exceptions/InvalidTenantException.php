<?php

namespace Ahs12\Setanjo\Exceptions;

use InvalidArgumentException;

class InvalidTenantException extends InvalidArgumentException
{
    protected $tenant;

    protected $allowedTypes;

    public function __construct(string $message = '', $tenant = null, array $allowedTypes = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->tenant = $tenant;
        $this->allowedTypes = $allowedTypes;
    }

    /**
     * Create exception for strict mode validation failure
     */
    public static function forStrictMode($tenant, string $expectedClass): self
    {
        $actualClass = is_object($tenant) ? get_class($tenant) : gettype($tenant);
        $message = "Invalid tenant type. Expected instance of [{$expectedClass}], got [{$actualClass}].";

        return new self($message, $tenant, [$expectedClass]);
    }

    /**
     * Create exception for polymorphic mode validation failure
     */
    public static function forPolymorphicMode($tenant, array $allowedClasses): self
    {
        $actualClass = is_object($tenant) ? get_class($tenant) : gettype($tenant);
        $allowed = implode(', ', $allowedClasses);
        $message = "Invalid tenant type. Expected one of [{$allowed}], got [{$actualClass}].";

        return new self($message, $tenant, $allowedClasses);
    }

    /**
     * Create exception for tenant not found
     */
    public static function notFound(string $modelClass, $tenantId): self
    {
        $message = "Tenant not found: {$modelClass}#{$tenantId}";

        return new self($message);
    }

    /**
     * Create exception for missing configuration
     */
    public static function missingConfiguration(string $configKey): self
    {
        $message = "Tenant configuration missing: {$configKey}";

        return new self($message);
    }

    public static function missingModelClassPolymorphic(): self
    {
        $message = 'Model class required in polymorphic mode';

        return new self($message);
    }

    /**
     * Get the invalid tenant object/value
     */
    public function getTenant()
    {
        return $this->tenant;
    }

    /**
     * Get the allowed tenant types
     */
    public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }

    /**
     * Get tenant class name if object
     */
    public function getTenantClass(): ?string
    {
        return is_object($this->tenant) ? get_class($this->tenant) : null;
    }
}

<?php

namespace Ahs12\Setanjo\Exceptions;

use Ahs12\Setanjo\Enums\SettingType;
use InvalidArgumentException;

class InvalidSettingValueException extends InvalidArgumentException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        protected string $settingKey = '',
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function cannotCoerce(string $key, SettingType $type, mixed $value): self
    {
        $given = get_debug_type($value);

        return new self(
            "Setting [{$key}] expects a value of type [{$type->value}], [{$given}] given.",
            $key,
            ['value' => ["The value must be of type {$type->value}."]],
        );
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function failedValidation(string $key, array $errors): self
    {
        $first = collect($errors)->flatten()->first() ?? 'The value is invalid.';

        return new self("Setting [{$key}] is invalid: {$first}", $key, $errors);
    }

    public static function scopeNotAllowed(string $key, string $scopeClass): self
    {
        return new self(
            "Setting [{$key}] cannot be overridden for scope [{$scopeClass}].",
            $key,
            ['scope' => ["Overrides for {$scopeClass} are not allowed."]],
        );
    }

    public static function isFeature(string $key): self
    {
        return new self(
            "[{$key}] is a feature flag; use Settings::feature('{$key}') instead of set().",
            $key,
            ['key' => ['Feature flags are stored separately from settings.']],
        );
    }

    public static function metaTooLarge(string $key, int $bytes, int $max): self
    {
        return new self(
            "Meta for setting [{$key}] is {$bytes} bytes, the maximum is {$max} bytes.",
            $key,
            ['meta' => ["The meta may not be greater than {$max} bytes."]],
        );
    }

    public static function invalidRule(string $key, string $field, string $message): self
    {
        return new self("Feature [{$key}]: {$message}", $key, [$field => [$message]]);
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

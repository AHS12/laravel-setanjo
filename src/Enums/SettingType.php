<?php

namespace Ahs12\Setanjo\Enums;

use Ahs12\Setanjo\Exceptions\InvalidSettingValueException;
use Stringable;

enum SettingType: string
{
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case ARRAY = 'array';
    case JSON = 'json';
    case OBJECT = 'object';

    public static function fromValue($value): self
    {
        return match (true) {
            is_bool($value) => self::BOOLEAN,
            is_int($value) => self::INTEGER,
            is_float($value) => self::FLOAT,
            is_array($value) => self::ARRAY ,
            is_object($value) => self::OBJECT,
            self::isJsonString($value) => self::JSON,
            default => self::STRING,
        };
    }

    /**
     * Convert a raw database string into the native PHP value for this type.
     */
    public function cast(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::BOOLEAN => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::INTEGER => (int) $raw,
            self::FLOAT => (float) $raw,
            self::ARRAY , self::JSON => json_decode($raw, true),
            self::OBJECT => json_decode($raw),
            self::STRING => $raw,
        };
    }

    /**
     * Convert a native PHP value into the string stored in the database.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::ARRAY , self::JSON, self::OBJECT => json_encode($value, JSON_THROW_ON_ERROR),
            self::BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Coerce loosely typed input (form posts, CLI, JSON imports) into this type.
     *
     * Used when a key has a definition, so the stored type is pinned instead of
     * being guessed from whatever value happens to be passed in.
     *
     * @throws InvalidSettingValueException
     */
    public function coerce(mixed $value, string $key = ''): mixed
    {
        if ($value === null) {
            return null;
        }

        $coerced = match ($this) {
            self::BOOLEAN => is_bool($value)
                ? $value
                : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            self::INTEGER => match (true) {
                is_int($value) => $value,
                is_bool($value) => (int) $value,
                is_numeric($value) && (float) $value == (int) $value => (int) $value,
                default => null,
            },
            self::FLOAT => is_numeric($value) ? (float) $value : null,
            self::STRING => is_scalar($value) || $value instanceof Stringable ? (string) $value : null,
            self::ARRAY => match (true) {
                is_array($value) => $value,
                is_string($value) && is_array($decoded = json_decode($value, true)) => $decoded,
                default => null,
            },
            self::OBJECT => match (true) {
                is_object($value) => $value,
                is_array($value) => json_decode(json_encode($value, JSON_THROW_ON_ERROR)),
                is_string($value) && is_object($decoded = json_decode($value)) => $decoded,
                default => null,
            },
            self::JSON => $value,
        };

        if ($coerced === null) {
            throw InvalidSettingValueException::cannotCoerce($key, $this, $value);
        }

        return $coerced;
    }

    /**
     * Check if a string is valid JSON
     */
    private static function isJsonString($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

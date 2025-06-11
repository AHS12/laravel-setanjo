<?php

namespace Ahs12\Setanjo\Enums;

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

<?php

namespace Ahs12\Setanjo\Support;

use Stringable;

/**
 * Turns setting values into printable strings (used by the @setting Blade directive).
 */
class Display
{
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        };
    }
}

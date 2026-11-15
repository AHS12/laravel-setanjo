<?php

namespace Ahs12\Setanjo\Exceptions;

use InvalidArgumentException;

class UndefinedFeatureException extends InvalidArgumentException
{
    public static function forKey(string $key): self
    {
        return new self("Feature [{$key}] is not defined. Register it as a feature definition or disable setanjo.features.strict.");
    }
}

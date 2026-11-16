<?php

namespace Ahs12\Setanjo\Events;

use Ahs12\Setanjo\ValueObjects\FeatureRules;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A flag's schedule, rollout, required flags or archive state changed.
 */
class FeatureRulesChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly FeatureRules $previous,
        public readonly FeatureRules $rules,
    ) {}
}

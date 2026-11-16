<?php

namespace Ahs12\Setanjo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class FeatureToggled
{
    use Dispatchable;

    /**
     * @param  bool|null  $state  true/false for an explicit value, null when the scope now inherits
     * @param  bool|null  $previous  the explicit state before the change (null: it inherited)
     */
    public function __construct(
        public readonly string $key,
        public readonly ?Model $scope,
        public readonly ?bool $state,
        public readonly ?bool $previous = null,
    ) {}
}

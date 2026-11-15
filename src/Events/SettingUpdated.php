<?php

namespace Ahs12\Setanjo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class SettingUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly ?Model $scope,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
        public readonly bool $created,
    ) {}
}

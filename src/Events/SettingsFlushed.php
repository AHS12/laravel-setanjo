<?php

namespace Ahs12\Setanjo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class SettingsFlushed
{
    use Dispatchable;

    public function __construct(public readonly ?Model $scope) {}
}

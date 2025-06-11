<?php

namespace Ahs12\Setanjo\Traits;

use Ahs12\Setanjo\SetanjoManager;

trait HasSettings
{
    public function settings(): SetanjoManager
    {
        return app('setanjo')->for($this);
    }
}

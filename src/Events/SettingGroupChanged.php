<?php

namespace Ahs12\Setanjo\Events;

use Ahs12\Setanjo\Enums\GroupAction;
use Ahs12\Setanjo\Models\SettingGroup;
use Illuminate\Foundation\Events\Dispatchable;

class SettingGroupChanged
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly GroupAction $action,
        public readonly ?SettingGroup $group,
        public readonly array $context = [],
    ) {}
}

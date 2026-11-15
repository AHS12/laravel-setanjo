<?php

namespace Ahs12\Setanjo\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SettingsSynced
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $updated
     * @param  array<int, string>  $pruned
     */
    public function __construct(
        public readonly array $added,
        public readonly array $updated,
        public readonly array $pruned,
    ) {}
}

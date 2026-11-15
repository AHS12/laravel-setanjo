<?php

namespace Ahs12\Setanjo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class SettingMetaUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $oldMeta
     * @param  array<string, mixed>|null  $newMeta
     */
    public function __construct(
        public readonly string $key,
        public readonly ?Model $scope,
        public readonly ?array $oldMeta,
        public readonly ?array $newMeta,
    ) {}
}

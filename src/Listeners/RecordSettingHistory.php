<?php

namespace Ahs12\Setanjo\Listeners;

use Ahs12\Setanjo\Enums\HistoryAction;
use Ahs12\Setanjo\Events\FeatureToggled;
use Ahs12\Setanjo\Events\SettingForgotten;
use Ahs12\Setanjo\Events\SettingMetaUpdated;
use Ahs12\Setanjo\Events\SettingUpdated;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes setanjo_history rows while `setanjo.history.enabled` is true.
 */
class RecordSettingHistory
{
    public const MASK = '********';

    public function __construct(protected SetanjoManager $setanjo) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(SettingUpdated::class, [self::class, 'handleUpdated']);
        $events->listen(SettingForgotten::class, [self::class, 'handleForgotten']);
        $events->listen(SettingMetaUpdated::class, [self::class, 'handleMetaUpdated']);
        $events->listen(FeatureToggled::class, [self::class, 'handleFeatureToggled']);
    }

    public function handleFeatureToggled(FeatureToggled $event): void
    {
        $this->record(
            $event->key,
            $event->scope,
            HistoryAction::TOGGLE,
            newValue: $event->state === null ? null : ($event->state ? 'true' : 'false'),
        );
    }

    public function handleUpdated(SettingUpdated $event): void
    {
        $this->record(
            $event->key,
            $event->scope,
            HistoryAction::SET,
            oldValue: $this->serialize($event->key, $event->oldValue),
            newValue: $this->serialize($event->key, $event->newValue),
        );
    }

    public function handleForgotten(SettingForgotten $event): void
    {
        $this->record(
            $event->key,
            $event->scope,
            HistoryAction::FORGET,
            oldValue: $this->serialize($event->key, $event->oldValue),
        );
    }

    public function handleMetaUpdated(SettingMetaUpdated $event): void
    {
        $this->record($event->key, $event->scope, HistoryAction::META, oldMeta: $event->oldMeta, newMeta: $event->newMeta);
    }

    /**
     * @param  array<string, mixed>|null  $oldMeta
     * @param  array<string, mixed>|null  $newMeta
     */
    protected function record(
        string $key,
        ?Model $scope,
        HistoryAction $action,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $oldMeta = null,
        ?array $newMeta = null,
    ): void {
        if (! config('setanjo.history.enabled', false)) {
            return;
        }

        $causer = function_exists('auth') ? auth()->user() : null;

        SettingHistory::query()->create([
            'setting_key' => $key,
            'scope_type' => $scope ? $scope::class : null,
            'scope_id' => $scope?->getKey(),
            'action' => $action->value,
            'causer_type' => $causer instanceof Model ? $causer::class : null,
            'causer_id' => $causer instanceof Model ? $causer->getKey() : null,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'old_meta' => $oldMeta,
            'new_meta' => $newMeta,
        ]);
    }

    protected function serialize(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($this->setanjo->meta($key, 'sensitive', false)) {
            return self::MASK;
        }

        return is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
    }
}

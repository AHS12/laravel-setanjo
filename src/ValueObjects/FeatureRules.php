<?php

namespace Ahs12\Setanjo\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The rules stored on a flag row, applied after per-record overrides:
 *
 * - a schedule window (`enabled_from` inclusive, `enabled_until` exclusive): outside it the flag is off;
 * - `requires`: other flags that must be on for the same subject;
 * - `rollout`: the share of records (0–100) that get the flag, by a stable bucket per record;
 * - `archived_at`: an archived flag is hidden from the lists and always reads as off.
 */
final readonly class FeatureRules
{
    /**
     * @param  array<int, string>  $requires
     */
    public function __construct(
        public ?CarbonImmutable $enabledFrom = null,
        public ?CarbonImmutable $enabledUntil = null,
        public ?int $rollout = null,
        public array $requires = [],
        public ?CarbonImmutable $archivedAt = null,
    ) {}

    /**
     * From a flag record (FeatureRepositoryInterface::flag()) or an array in the toArray() shape.
     *
     * @param  array<string, mixed>|null  $record
     */
    public static function fromArray(?array $record): self
    {
        return new self(
            self::time($record['enabled_from'] ?? null),
            self::time($record['enabled_until'] ?? null),
            isset($record['rollout']) ? (int) $record['rollout'] : null,
            array_values(array_map('strval', (array) ($record['requires'] ?? []))),
            self::time($record['archived_at'] ?? null),
        );
    }

    /**
     * Copy with some rules replaced. Keys: enabled_from, enabled_until, rollout, requires, archived_at.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function hasSchedule(): bool
    {
        return $this->enabledFrom !== null || $this->enabledUntil !== null;
    }

    /**
     * Where `now` is relative to the schedule: pending (before the start), open, ended, or null without one.
     */
    public function window(?DateTimeInterface $now = null): ?string
    {
        if (! $this->hasSchedule()) {
            return null;
        }

        $now = CarbonImmutable::instance($now ?? now());

        return match (true) {
            $this->enabledFrom !== null && $now->lt($this->enabledFrom) => 'pending',
            $this->enabledUntil !== null && $now->gte($this->enabledUntil) => 'ended',
            default => 'open',
        };
    }

    public function inWindow(?DateTimeInterface $now = null): bool
    {
        return in_array($this->window($now), [null, 'open'], true);
    }

    /**
     * Whether the rollout limits who gets the flag (null and 100 mean everyone).
     */
    public function limitsRollout(): bool
    {
        return $this->rollout !== null && $this->rollout < 100;
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === (new self)->toArray();
    }

    /**
     * @return array{enabled_from: ?string, enabled_until: ?string, rollout: ?int, requires: array<int, string>, archived_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'enabled_from' => $this->enabledFrom?->toIso8601String(),
            'enabled_until' => $this->enabledUntil?->toIso8601String(),
            'rollout' => $this->rollout,
            'requires' => $this->requires,
            'archived_at' => $this->archivedAt?->toIso8601String(),
        ];
    }

    /**
     * Column values for the flag row.
     *
     * @return array{enabled_from: ?CarbonImmutable, enabled_until: ?CarbonImmutable, rollout: ?int, requires: ?array<int, string>, archived_at: ?CarbonImmutable}
     */
    public function toAttributes(): array
    {
        return [
            'enabled_from' => $this->enabledFrom,
            'enabled_until' => $this->enabledUntil,
            'rollout' => $this->rollout,
            'requires' => $this->requires === [] ? null : $this->requires,
            'archived_at' => $this->archivedAt,
        ];
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse((string) $value);

        // Eloquent stores timestamps without an offset: keep every time in the app timezone.
        return $time->setTimezone((string) config('app.timezone', 'UTC'));
    }
}

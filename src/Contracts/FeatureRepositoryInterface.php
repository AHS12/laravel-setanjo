<?php

namespace Ahs12\Setanjo\Contracts;

use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Storage for feature flags (global state) and their per-scope overrides.
 */
interface FeatureRepositoryInterface
{
    /**
     * Every stored flag keyed by feature key.
     *
     * Record shape: id, enabled (?bool), name, description, group_id, sort_order, meta, and the
     * rules: enabled_from, enabled_until, archived_at (ISO 8601 or null), rollout (?int), requires (string[]).
     *
     * @return array<string, array<string, mixed>>
     */
    public function flags(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function flag(string $key): ?array;

    /**
     * Explicit states stored for one scope, keyed by feature key.
     *
     * @return array<string, bool>
     */
    public function overrides(Model $scope): array;

    /**
     * Load overrides of several scopes in one query.
     *
     * @param  array<int, Model>  $scopes
     */
    public function loadOverrides(array $scopes): void;

    /**
     * Set the global state (null = use the definition default). Creates the flag row when missing.
     *
     * @param  array<string, mixed>  $createWith  attributes for a new row (name, description, group_id, meta)
     */
    public function setGlobal(string $key, ?bool $enabled, array $createWith = []): void;

    /**
     * @param  array<string, mixed>  $createWith
     */
    public function setOverride(string $key, Model $scope, bool $enabled, array $createWith = []): void;

    /**
     * Remove a scope override. Returns whether one existed.
     */
    public function forgetOverride(string $key, Model $scope): bool;

    /**
     * Update flag attributes (name, description, group_id, sort_order, meta) and rules
     * (enabled_from, enabled_until, rollout, requires, archived_at).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $createWith
     */
    public function updateAttributes(string $key, array $attributes, array $createWith = []): void;

    /**
     * Delete a flag and all its overrides.
     */
    public function delete(string $key): void;

    public function find(string $key): ?Feature;

    /**
     * Number of overrides per feature key.
     *
     * @return array<string, int>
     */
    public function overrideCounts(): array;

    /**
     * Overrides of one feature, with their scope models.
     *
     * @return Collection<int, FeatureOverride>
     */
    public function overridesOf(string $key): Collection;

    public function forgetCache(?Model $scope = null): void;

    public function flushCache(): void;

    public function reset(): void;
}

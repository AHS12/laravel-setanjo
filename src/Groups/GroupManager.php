<?php

namespace Ahs12\Setanjo\Groups;

use Ahs12\Setanjo\Contracts\SettingsRepositoryInterface;
use Ahs12\Setanjo\Enums\GroupAction;
use Ahs12\Setanjo\Events\SettingGroupChanged;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Create, order and assign settings groups.
 *
 * Group membership and ordering live on the global row of each setting, so they are
 * shared by every scope.
 */
class GroupManager
{
    public function __construct(
        protected SetanjoManager $manager,
        protected SettingsRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, SettingGroup>
     */
    public function all(): Collection
    {
        return SettingGroup::query()->ordered()->get();
    }

    public function find(SettingGroup|int|string $group): ?SettingGroup
    {
        if ($group instanceof SettingGroup) {
            return $group;
        }

        return is_int($group) || ctype_digit($group)
            ? SettingGroup::query()->find((int) $group)
            : SettingGroup::query()->where('slug', $group)->first();
    }

    public function findOrFail(SettingGroup|int|string $group): SettingGroup
    {
        return $this->find($group)
            ?? throw (new ModelNotFoundException)->setModel(SettingGroup::class, [$group]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SettingGroup
    {
        if (empty($attributes['name'])) {
            throw new InvalidArgumentException('A settings group needs a name.');
        }

        $slug = Str::slug($attributes['slug'] ?? $attributes['name']);
        $this->ensureSlugAvailable($slug);

        $group = SettingGroup::query()->create([
            'name' => $attributes['name'],
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'icon' => $attributes['icon'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? ((int) SettingGroup::query()->max('sort_order')) + 1,
            'meta' => $attributes['meta'] ?? null,
        ]);

        event(new SettingGroupChanged(GroupAction::CREATED, $group));

        return $group;
    }

    /**
     * Find a group by slug, creating it when missing.
     */
    public function ensure(string $slug, ?string $name = null): SettingGroup
    {
        $slug = Str::slug($slug);

        return SettingGroup::query()->where('slug', $slug)->first()
            ?? $this->create(['name' => $name ?? Str::headline($slug), 'slug' => $slug]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SettingGroup|int|string $group, array $attributes): SettingGroup
    {
        $group = $this->findOrFail($group);

        if (isset($attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['slug']);
            $this->ensureSlugAvailable($attributes['slug'], $group->getKey());
        }

        $group->update($attributes);

        event(new SettingGroupChanged(GroupAction::UPDATED, $group));

        return $group;
    }

    /**
     * Delete a group; its settings become ungrouped.
     */
    public function delete(SettingGroup|int|string $group): void
    {
        $group = $this->findOrFail($group);

        Setting::query()->where('group_id', $group->getKey())->update(['group_id' => null]);
        Feature::query()->where('group_id', $group->getKey())->update(['group_id' => null]);
        $group->delete();
        $this->manager->flushCache();

        event(new SettingGroupChanged(GroupAction::DELETED, $group));
    }

    /**
     * Persist a new group order.
     *
     * @param  array<int, SettingGroup|int|string>  $groups  groups in their new order
     */
    public function reorder(array $groups): void
    {
        foreach (array_values($groups) as $position => $group) {
            $this->findOrFail($group)->update(['sort_order' => $position]);
        }

        event(new SettingGroupChanged(GroupAction::REORDERED, null, ['count' => count($groups)]));
    }

    /**
     * Move a setting into a group (null = ungrouped), optionally at a position.
     */
    public function assign(string $key, SettingGroup|int|string|null $group, ?int $position = null): void
    {
        $group = $group === null ? null : $this->findOrFail($group);
        $attributes = ['group_id' => $group?->getKey()];

        if ($position !== null) {
            $attributes['sort_order'] = $position;
        }

        $this->repository->updateAttributes(
            $key,
            $attributes,
            null,
            $this->manager->createWith($this->manager->definitions()->get($key)),
        );

        event(new SettingGroupChanged(GroupAction::ASSIGNED, $group, ['key' => $key, 'position' => $position]));
    }

    /**
     * Persist the order of settings inside a group, moving them into it if needed.
     *
     * @param  array<int, string>  $keys  setting keys in their new order
     */
    public function reorderSettings(SettingGroup|int|string|null $group, array $keys): void
    {
        foreach (array_values($keys) as $position => $key) {
            $this->assign($key, $group, $position);
        }
    }

    /**
     * Setting keys in a group (null = ungrouped), in display order.
     *
     * @return array<int, string>
     */
    public function keys(SettingGroup|int|string|null $group): array
    {
        $groupId = $group === null ? null : $this->findOrFail($group)->getKey();

        return collect($this->repository->records(null))
            ->filter(fn (array $record) => $record['group_id'] === $groupId)
            ->sortBy('sort_order')
            ->keys()
            ->all();
    }

    /**
     * Move a feature flag into a group (null = ungrouped), optionally at a position.
     */
    public function assignFeature(string $key, SettingGroup|int|string|null $group, ?int $position = null): void
    {
        $group = $group === null ? null : $this->findOrFail($group);
        $attributes = ['group_id' => $group?->getKey()];

        if ($position !== null) {
            $attributes['sort_order'] = $position;
        }

        $this->manager->featureRepository()->updateAttributes(
            $key,
            $attributes,
            $this->manager->featureAttributes($this->manager->definitions()->get($key)),
        );

        event(new SettingGroupChanged(GroupAction::ASSIGNED, $group, ['feature' => $key, 'position' => $position]));
    }

    /**
     * @param  array<int, string>  $keys  feature keys in their new order
     */
    public function reorderFeatures(SettingGroup|int|string|null $group, array $keys): void
    {
        foreach (array_values($keys) as $position => $key) {
            $this->assignFeature($key, $group, $position);
        }
    }

    /**
     * Feature keys in a group (null = ungrouped), in display order.
     *
     * @return array<int, string>
     */
    public function featureKeys(SettingGroup|int|string|null $group): array
    {
        $groupId = $group === null ? null : $this->findOrFail($group)->getKey();

        return collect($this->manager->featureRepository()->flags())
            ->filter(fn (array $record) => $record['group_id'] === $groupId)
            ->sortBy('sort_order')
            ->keys()
            ->all();
    }

    protected function ensureSlugAvailable(string $slug, ?int $ignoreId = null): void
    {
        $exists = SettingGroup::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("A settings group with slug [{$slug}] already exists.");
        }
    }
}

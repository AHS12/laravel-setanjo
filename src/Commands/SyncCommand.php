<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Contracts\SettingDefinition;
use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Events\SettingsSynced;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Console\Command;
use Throwable;

class SyncCommand extends Command
{
    protected $signature = 'setanjo:sync
                            {--dry-run : Show what would change without writing anything}
                            {--prune : Delete rows (all scopes) whose key has no definition}
                            {--reset-meta : Clear meta stored in the database so code meta applies again}
                            {--reset-groups : Move every setting back to the group declared in code}
                            {--force-types : Re-type stored values whose type differs from the definition}';

    protected $description = 'Sync setting and feature-flag definitions into the database';

    /** @var array<int, string> */
    protected array $added = [];

    /** @var array<int, string> */
    protected array $updated = [];

    /** @var array<int, string> */
    protected array $pruned = [];

    public function handle(SetanjoManager $setanjo): int
    {
        $definitions = $setanjo->definitions();
        $dryRun = (bool) $this->option('dry-run');
        $this->added = $this->updated = $this->pruned = [];

        if ($definitions->isEmpty()) {
            $this->warn('No setting definitions registered. Add them to setanjo.definitions.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('DRY RUN: no changes will be written.');
        }

        foreach ($definitions->settings() as $definition) {
            $this->syncDefinition($setanjo, $definition, $dryRun);
        }

        foreach ($definitions->features() as $definition) {
            $this->syncFeature($setanjo, $definition, $dryRun);
        }

        $this->warnAboutFlagsInSettingsTable($definitions);

        if ($this->option('prune')) {
            $this->prune($definitions, $dryRun);
            $this->pruneFeatures($setanjo, $definitions, $dryRun);
        }

        if ($dryRun) {
            $this->info(sprintf('DRY RUN COMPLETE: would add %d, update %d, prune %d.', count($this->added), count($this->updated), count($this->pruned)));

            return self::SUCCESS;
        }

        $setanjo->flushCache();

        event(new SettingsSynced($this->added, $this->updated, $this->pruned));

        $this->info(sprintf('Settings synced: %d added, %d updated, %d pruned.', count($this->added), count($this->updated), count($this->pruned)));

        return self::SUCCESS;
    }

    protected function syncDefinition(SetanjoManager $setanjo, SettingDefinition $definition, bool $dryRun): void
    {
        $key = $definition->key();
        $row = Setting::global()->where('key', $key)->first();
        $groupSlug = DefinitionRegistry::groupSlug($definition);

        if ($row === null) {
            $this->line("Add: {$key}");
            $this->added[] = $key;

            if (! $dryRun) {
                $setanjo->repository()->set($key, $definition->default(), null, $definition->type());
                $groupId = $groupSlug ? $setanjo->groups()->ensure($groupSlug, DefinitionRegistry::groupName($definition))->getKey() : null;
                $setanjo->repository()->updateAttributes($key, array_filter([
                    'group_id' => $groupId,
                    'description' => $definition->description(),
                ], fn ($value) => $value !== null));
            }

            return;
        }

        $changes = [];
        $attributes = [];

        $storedType = $row->getRawOriginal('type');
        if ($storedType !== $definition->type()->value) {
            if ($this->option('force-types')) {
                $changes[] = "type {$storedType} -> {$definition->type()->value}";
            } else {
                $this->warn("Type mismatch: {$key} is stored as [{$storedType}], defined as [{$definition->type()->value}]. Use --force-types to convert.");
            }
        }

        if ($groupSlug !== null && ($row->group_id === null || $this->option('reset-groups'))) {
            $currentSlug = $row->group_id ? $setanjo->groups()->find($row->group_id)?->slug : null;

            if ($currentSlug !== $groupSlug) {
                $changes[] = "group -> {$groupSlug}";
                if (! $dryRun) {
                    $attributes['group_id'] = $setanjo->groups()->ensure($groupSlug, DefinitionRegistry::groupName($definition))->getKey();
                }
            }
        }

        if ($this->option('reset-meta') && $row->meta !== null) {
            $changes[] = 'meta reset';
            $attributes['meta'] = null;
        }

        if ($row->description === null && $definition->description() !== null) {
            $attributes['description'] = $definition->description();
        }

        if ($changes === []) {
            if (! $dryRun && $attributes !== []) {
                $setanjo->repository()->updateAttributes($key, $attributes);
            }

            return;
        }

        $this->line("Update: {$key} (".implode(', ', $changes).')');
        $this->updated[] = $key;

        if ($dryRun) {
            return;
        }

        if ($this->option('force-types') && $storedType !== $definition->type()->value) {
            $this->convertTypes($setanjo, $definition);
        }

        if ($attributes !== []) {
            $setanjo->repository()->updateAttributes($key, $attributes);
        }
    }

    /**
     * Re-type every stored row (all scopes) of a key, falling back to the default when a value cannot be converted.
     */
    protected function convertTypes(SetanjoManager $setanjo, SettingDefinition $definition): void
    {
        Setting::query()->where('key', $definition->key())->get()->each(function (Setting $row) use ($definition) {
            try {
                $value = $definition->type()->coerce($row->value, $definition->key());
            } catch (Throwable) {
                $this->warn("Could not convert {$definition->key()} (#{$row->id}); reset to default.");
                $value = $definition->default();
            }

            $row->type = $definition->type();
            $row->value = $value;
            $row->save();
        });
    }

    /**
     * Create the flag row for a feature definition, align its group and meta.
     */
    protected function syncFeature(SetanjoManager $setanjo, SettingDefinition $definition, bool $dryRun): void
    {
        $key = $definition->key();
        $repository = $setanjo->featureRepository();
        $groupSlug = DefinitionRegistry::groupSlug($definition);
        $feature = $repository->find($key);

        $groupId = fn () => $groupSlug
            ? $setanjo->groups()->ensure($groupSlug, DefinitionRegistry::groupName($definition))->getKey()
            : null;

        if ($feature === null) {
            $this->line("Add feature: {$key}");
            $this->added[] = $key;

            if (! $dryRun) {
                $repository->updateAttributes($key, [
                    ...$setanjo->featureAttributes($definition),
                    'group_id' => $groupId(),
                ]);
            }

            return;
        }

        $changes = [];
        $attributes = [];

        if ($groupSlug !== null && ($feature->group_id === null || $this->option('reset-groups'))) {
            $currentSlug = $feature->group_id ? $setanjo->groups()->find($feature->group_id)?->slug : null;

            if ($currentSlug !== $groupSlug) {
                $changes[] = "group -> {$groupSlug}";
                if (! $dryRun) {
                    $attributes['group_id'] = $groupId();
                }
            }
        }

        if ($this->option('reset-meta') && $feature->meta !== null) {
            $changes[] = 'meta reset';
            $attributes['meta'] = null;
        }

        if ($feature->name === null) {
            $attributes['name'] = $definition->label();
        }
        if ($feature->description === null && $definition->description() !== null) {
            $attributes['description'] = $definition->description();
        }

        if ($changes !== []) {
            $this->line("Update feature: {$key} (".implode(', ', $changes).')');
            $this->updated[] = $key;
        }

        if (! $dryRun && $attributes !== []) {
            $repository->updateAttributes($key, $attributes);
        }
    }

    /**
     * Flags still stored as settings rows (v2 / app-built flags) need moving first.
     */
    protected function warnAboutFlagsInSettingsTable(DefinitionRegistry $definitions): void
    {
        $keys = array_keys($definitions->features());

        if ($keys === []) {
            return;
        }

        $found = Setting::query()->whereIn('key', $keys)->distinct()->pluck('key');

        if ($found->isNotEmpty()) {
            $this->warn('Feature flags found in the settings table: '.$found->implode(', ').'. Run setanjo:migrate-feature-flags to move them.');
        }
    }

    protected function pruneFeatures(SetanjoManager $setanjo, DefinitionRegistry $definitions, bool $dryRun): void
    {
        $stale = collect(array_keys($setanjo->featureRepository()->flags()))
            ->reject(fn (string $key) => $definitions->get($key)?->isFeature() === true)
            ->values();

        foreach ($stale as $key) {
            $this->line("Prune feature: {$key}");
            $this->pruned[] = $key;

            if (! $dryRun) {
                $setanjo->featureRepository()->delete($key);
            }
        }
    }

    protected function prune(DefinitionRegistry $definitions, bool $dryRun): void
    {
        // Rows of feature keys are left alone: they are flags waiting for setanjo:migrate-feature-flags.
        $stale = Setting::query()
            ->select('key')
            ->distinct()
            ->pluck('key')
            ->reject(fn (string $key) => $definitions->has($key))
            ->values();

        foreach ($stale as $key) {
            $this->line("Prune: {$key}");
            $this->pruned[] = $key;
        }

        if (! $dryRun && $stale->isNotEmpty()) {
            Setting::query()->whereIn('key', $stale->all())->delete();
        }
    }
}

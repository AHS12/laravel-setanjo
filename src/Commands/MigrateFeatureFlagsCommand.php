<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves flags that were stored as boolean settings rows (v2 / app-built flags) into the
 * feature tables: the global row becomes the flag's global state, scoped rows become overrides.
 */
class MigrateFeatureFlagsCommand extends Command
{
    protected $signature = 'setanjo:migrate-feature-flags
                            {--key=* : Keys to move (default: every feature definition)}
                            {--keep : Keep the old rows in the settings table}
                            {--dry-run : Show what would move without writing}';

    protected $description = 'Move feature flags stored as settings rows into the feature tables';

    public function handle(SetanjoManager $setanjo): int
    {
        $keys = $this->option('key') ?: array_keys($setanjo->definitions()->features());

        if ($keys === []) {
            $this->warn('No feature keys given and no feature definitions registered.');

            return self::SUCCESS;
        }

        $column = config('setanjo.tenantable_column', 'tenantable');
        $rows = Setting::query()->whereIn('key', $keys)->get();

        if ($rows->isEmpty()) {
            $this->info('No feature flags found in the settings table.');

            return self::SUCCESS;
        }

        foreach ($rows->groupBy('key') as $key => $group) {
            $this->line(sprintf('Move: %s (%d row%s)', $key, $group->count(), $group->count() === 1 ? '' : 's'));
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would move {$rows->count()} rows.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $column, $setanjo) {
            foreach ($rows->groupBy('key') as $key => $group) {
                $definition = $setanjo->definitions()->get($key);

                $feature = Feature::query()->firstOrCreate(['key' => $key], [
                    'name' => $definition?->label(),
                    'description' => $definition?->description() ?? $group->first()->description,
                    'group_id' => $group->firstWhere($column.'_type', null)?->group_id,
                ]);

                foreach ($group as $row) {
                    $scopeType = $row->getAttribute($column.'_type');
                    $enabled = filter_var($row->getRawOriginal('value'), FILTER_VALIDATE_BOOLEAN);

                    if ($scopeType === null) {
                        $feature->update(['enabled' => $enabled]);

                        continue;
                    }

                    FeatureOverride::query()->updateOrCreate([
                        'feature_id' => $feature->id,
                        'scope_type' => $scopeType,
                        'scope_id' => $row->getAttribute($column.'_id'),
                    ], ['enabled' => $enabled]);
                }
            }

            if (! $this->option('keep')) {
                Setting::query()->whereIn('id', $rows->modelKeys())->delete();
            }
        });

        $setanjo->flushCache();

        $this->info("Moved {$rows->count()} rows into the feature tables.");

        return self::SUCCESS;
    }
}

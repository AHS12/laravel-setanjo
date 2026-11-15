<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class ImportCommand extends Command
{
    protected $signature = 'setanjo:import
                            {path : JSON file created by setanjo:export}
                            {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import settings, meta and groups from a JSON export';

    public function handle(SetanjoManager $setanjo): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($payload) || ! isset($payload['settings']) || ! is_array($payload['settings'])) {
            $this->error('Not a setanjo export: missing "settings".');

            return self::FAILURE;
        }

        $groups = $payload['groups'] ?? [];
        $settings = $payload['settings'];
        $features = $payload['features'] ?? [];

        if ($this->option('dry-run')) {
            $this->info(sprintf('DRY RUN: would import %d groups, %d settings and %d features.', count($groups), count($settings), count($features)));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($groups, $settings, $features) {
            $groupIds = $this->importGroups($groups);
            $this->importSettings($settings, $groupIds);
            $this->importFeatures($features, $groupIds);
        });

        $setanjo->flushCache();

        $this->info(sprintf('Imported %d groups, %d settings and %d features.', count($groups), count($settings), count($features)));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<string, int>  $groupIds
     */
    protected function importFeatures(array $features, array $groupIds): void
    {
        foreach ($features as $item) {
            if (empty($item['key'])) {
                continue;
            }

            $feature = Feature::query()->updateOrCreate(['key' => $item['key']], [
                'name' => $item['name'] ?? null,
                'description' => $item['description'] ?? null,
                'enabled' => isset($item['enabled']) ? (bool) $item['enabled'] : null,
                'group_id' => isset($item['group']) ? ($groupIds[$item['group']] ?? null) : null,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'meta' => $item['meta'] ?? null,
            ]);

            foreach ($item['overrides'] ?? [] as $override) {
                if (empty($override['scope_type']) || ! isset($override['scope_id'])) {
                    continue;
                }

                FeatureOverride::query()->updateOrCreate([
                    'feature_id' => $feature->id,
                    'scope_type' => $override['scope_type'],
                    'scope_id' => $override['scope_id'],
                ], ['enabled' => (bool) ($override['enabled'] ?? false)]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<string, int> slug => id
     */
    protected function importGroups(array $groups): array
    {
        $ids = SettingGroup::query()->pluck('id', 'slug')->all();

        foreach ($groups as $group) {
            if (empty($group['slug']) || empty($group['name'])) {
                continue;
            }

            $model = SettingGroup::query()->updateOrCreate(['slug' => $group['slug']], [
                'name' => $group['name'],
                'description' => $group['description'] ?? null,
                'icon' => $group['icon'] ?? null,
                'sort_order' => (int) ($group['sort_order'] ?? 0),
                'meta' => $group['meta'] ?? null,
            ]);

            $ids[$model->slug] = $model->getKey();
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $settings
     * @param  array<string, int>  $groupIds
     */
    protected function importSettings(array $settings, array $groupIds): void
    {
        $column = config('setanjo.tenantable_column', 'tenantable');

        foreach ($settings as $item) {
            if (empty($item['key'])) {
                continue;
            }

            $type = SettingType::tryFrom((string) ($item['type'] ?? '')) ?? SettingType::STRING;

            $scopeType = $item['scope_type'] ?? null;
            $scopeId = $item['scope_id'] ?? null;

            $setting = Setting::query()
                ->forScopeKey($scopeType, $scopeId)
                ->where('key', $item['key'])
                ->first() ?? new Setting(['key' => $item['key']]);

            $setting->forceFill([
                $column.'_type' => $item['scope_type'] ?? null,
                $column.'_id' => $item['scope_id'] ?? null,
                'type' => $type,
                'description' => $item['description'] ?? null,
                'meta' => $item['meta'] ?? null,
                'group_id' => isset($item['group']) ? ($groupIds[$item['group']] ?? null) : null,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'is_locked' => (bool) ($item['is_locked'] ?? false),
            ]);

            // The export holds the raw stored string; write it untouched.
            $setting->setRawAttributes([...$setting->getAttributes(), 'value' => $item['value'] ?? null]);
            $setting->save();
        }
    }
}

<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\Models\Feature;
use Ahs12\Setanjo\Models\FeatureOverride;
use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\Models\SettingGroup;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    public const FORMAT_VERSION = 3;

    protected $signature = 'setanjo:export
                            {path? : File to write (prints to stdout when omitted)}
                            {--global : Export only global settings}
                            {--scope= : Export only one scope (format: App\\Models\\User:1)}';

    protected $description = 'Export settings, meta and groups as JSON';

    public function handle(): int
    {
        $column = config('setanjo.tenantable_column', 'tenantable');
        $groups = SettingGroup::query()->ordered()->get();
        $slugs = $groups->pluck('slug', 'id');

        $query = Setting::query()->orderBy('key');
        $scopeFilter = null; // null = every override, false = none, [type, id] = one scope

        if ($this->option('global')) {
            $query->global();
            $scopeFilter = false;
        } elseif ($scope = $this->option('scope')) {
            if (! preg_match('/^(.+):(\d+)$/', $scope, $matches)) {
                $this->error('Invalid scope. Use ModelClass:ID, e.g. App\\Models\\User:1');

                return self::FAILURE;
            }

            $query->forScopeKey($matches[1], (int) $matches[2]);
            $scopeFilter = [$matches[1], (int) $matches[2]];
        }

        $features = Feature::query()->ordered()->with('overrides')->get();

        $payload = [
            'setanjo' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'groups' => $groups->map(fn (SettingGroup $group) => [
                'slug' => $group->slug,
                'name' => $group->name,
                'description' => $group->description,
                'icon' => $group->icon,
                'sort_order' => $group->sort_order,
                'meta' => $group->meta,
            ])->values()->all(),
            'settings' => $query->get()->map(fn (Setting $setting) => [
                'key' => $setting->key,
                'type' => $setting->getRawOriginal('type'),
                'value' => $setting->getRawOriginal('value'),
                'description' => $setting->description,
                'meta' => $setting->meta,
                'group' => $setting->group_id ? $slugs->get($setting->group_id) : null,
                'sort_order' => (int) $setting->sort_order,
                'is_locked' => (bool) $setting->is_locked,
                'scope_type' => $setting->getAttribute($column.'_type'),
                'scope_id' => $setting->getAttribute($column.'_id'),
            ])->values()->all(),
            'features' => $features->map(fn (Feature $feature) => [
                'key' => $feature->key,
                'name' => $feature->name,
                'description' => $feature->description,
                'enabled' => $feature->enabled,
                'group' => $feature->group_id ? $slugs->get($feature->group_id) : null,
                'sort_order' => (int) $feature->sort_order,
                'meta' => $feature->meta,
                'overrides' => $scopeFilter === false ? [] : $feature->overrides
                    ->filter(fn (FeatureOverride $override) => $scopeFilter === null
                        || ($override->scope_type === $scopeFilter[0] && (int) $override->scope_id === $scopeFilter[1]))
                    ->map(fn (FeatureOverride $override) => [
                        'scope_type' => $override->scope_type,
                        'scope_id' => $override->scope_id,
                        'enabled' => (bool) $override->enabled,
                    ])->values()->all(),
            ])->values()->all(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($path = $this->argument('path')) {
            file_put_contents($path, $json);
            $this->info(sprintf('Exported %d settings, %d features and %d groups to %s', count($payload['settings']), count($payload['features']), count($payload['groups']), $path));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}

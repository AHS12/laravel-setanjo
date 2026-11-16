<?php

namespace Ahs12\Setanjo\Dashboard;

use Ahs12\Setanjo\Models\Setting;
use Ahs12\Setanjo\SetanjoManager;

/**
 * Differences between the definitions in code and what the database holds.
 */
class Drift
{
    public function __construct(protected SetanjoManager $manager, protected Catalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $definitions = $this->manager->definitions();
        $records = $this->manager->repository()->records(null);
        $flags = $this->manager->featureRepository()->flags();
        $storedKeys = Setting::query()->select('key')->distinct()->pluck('key')->all();

        $missing = [];
        $typeMismatch = [];
        foreach ($definitions->settings() as $key => $definition) {
            $record = $records[$key] ?? null;

            if ($record === null) {
                $missing[] = ['key' => $key, 'label' => $definition->label()];

                continue;
            }

            if ($record['type'] !== $definition->type()->value) {
                $typeMismatch[] = ['key' => $key, 'stored' => $record['type'], 'defined' => $definition->type()->value];
            }
        }

        $missingFeatures = [];
        foreach ($definitions->features() as $key => $definition) {
            if (! isset($flags[$key])) {
                $missingFeatures[] = ['key' => $key, 'label' => $definition->label()];
            }
        }

        return [
            'definitions' => count($definitions->all()),
            'missing' => $missing,
            'missing_features' => $missingFeatures,
            'type_mismatch' => $typeMismatch,
            'stale' => array_values(array_filter($storedKeys, fn (string $key) => ! $definitions->has($key))),
            'stale_features' => array_values(array_filter(
                array_keys($flags),
                fn (string $key) => $definitions->get($key)?->isFeature() !== true,
            )),
            'flags_in_settings' => array_values(array_intersect($storedKeys, array_keys($definitions->features()))),
            // Overrides of deleted records, and of model classes the dashboard does not know.
            ...$this->catalog->orphans(),
        ];
    }
}

<?php

namespace Ahs12\Setanjo\Commands;

use Ahs12\Setanjo\SetanjoManager;
use Illuminate\Console\Command;

class InstallDefaultsCommand extends Command
{
    protected $signature = 'setanjo:install-defaults 
                            {--force : Force reinstall of existing settings}';

    protected $description = 'Install default settings from configuration';

    public function handle(SetanjoManager $settings): int
    {
        $defaults = config('setanjo.defaults', []);

        if (empty($defaults)) {
            $this->info('No default settings configured.');

            return self::SUCCESS;
        }

        $force = $this->option('force');
        $installed = 0;
        $skipped = 0;

        foreach ($defaults as $key => $config) {
            if (! $force && $settings->has($key)) {
                $this->line("Skipped existing setting: {$key}");
                $skipped++;

                continue;
            }

            $value = $config['value'] ?? $config;
            $settings->set($key, $value);

            $this->info("Installed setting: {$key}");
            $installed++;
        }

        $this->info("Installation complete! Installed: {$installed}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}

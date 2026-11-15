<?php

use Ahs12\Setanjo\Definitions\DefinitionRegistry;
use Ahs12\Setanjo\Tests\Fixtures\TestSetting;
use Ahs12\Setanjo\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Register the TestSetting enum as definitions for the current test.
 */
function registerTestDefinitions(): DefinitionRegistry
{
    return app(DefinitionRegistry::class)->register(TestSetting::class);
}

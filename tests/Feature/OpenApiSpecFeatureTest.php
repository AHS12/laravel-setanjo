<?php

namespace Ahs12\Setanjo\Tests\Feature;

use Ahs12\Setanjo\Facades\Settings;
use Ahs12\Setanjo\Setanjo;
use Ahs12\Setanjo\SetanjoServiceProvider;
use Ahs12\Setanjo\Tests\Models\Company;
use Ahs12\Setanjo\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
 * Keeps docs/openapi.yaml honest: every API route is in the spec (and nothing else), every
 * $ref resolves, and real responses have exactly the properties the schemas list.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    registerTestDefinitions();
    Setanjo::auth(fn () => true);
    config()->set('setanjo.api.enabled', true);
    config()->set('setanjo.history.enabled', true);
    (fn () => $this->registerApi())->call(app()->getProvider(SetanjoServiceProvider::class));
    app('router')->getRoutes()->refreshNameLookups();
});

afterEach(function () {
    Setanjo::flush();
});

/**
 * @return array<string, mixed>
 */
function openApiSpec(): array
{
    return Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');
}

/**
 * The properties a schema lists, following allOf.
 *
 * @param  array<string, mixed>  $spec
 * @return array{0: array<int, string>, 1: array<int, string>} [required, all]
 */
function schemaKeys(array $spec, string $name): array
{
    $schema = $spec['components']['schemas'][$name];
    $required = $schema['required'] ?? [];
    $all = array_keys($schema['properties'] ?? []);

    foreach ($schema['allOf'] ?? [] as $part) {
        if (isset($part['$ref'])) {
            [$partRequired, $partAll] = schemaKeys($spec, basename($part['$ref']));
        } else {
            [$partRequired, $partAll] = [$part['required'] ?? [], array_keys($part['properties'] ?? [])];
        }
        $required = [...$required, ...$partRequired];
        $all = [...$all, ...$partAll];
    }

    return [array_values(array_unique($required)), array_values(array_unique($all))];
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $object
 */
function assertMatchesSchema(array $spec, string $name, array $object): void
{
    [$required, $all] = schemaKeys($spec, $name);
    $keys = array_keys($object);

    expect(array_diff($required, $keys))->toBe([], "{$name}: required properties missing from the response")
        ->and(array_values(array_diff($keys, $all)))->toBe([], "{$name}: response properties not in the spec");
}

it('documents exactly the routes of the API', function () {
    $documented = [];
    foreach (openApiSpec()['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            if ($method !== 'parameters') {
                $documented[] = strtoupper($method).' '.$path;
            }
        }
    }

    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with((string) $route->getName(), 'setanjo.http.'))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' /'.substr($route->uri(), strlen('api/setanjo/'))))
        ->all();

    sort($documented);
    sort($registered);

    expect($documented)->toBe($registered);
});

it('resolves every $ref', function () {
    $spec = openApiSpec();
    preg_match_all('/"\$ref":"#\/([^"]+)"/', (string) json_encode($spec, JSON_UNESCAPED_SLASHES), $matches);

    foreach (array_unique($matches[1]) as $pointer) {
        $node = $spec;
        foreach (explode('/', $pointer) as $part) {
            expect(is_array($node) && array_key_exists($part, $node))->toBeTrue("Unresolved \$ref #/{$pointer}");
            $node = $node[$part];
        }
    }

    expect($spec['openapi'])->toBe('3.1.0');
});

it('matches real responses', function () {
    $spec = openApiSpec();
    $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $company = Company::query()->create(['name' => 'Initech']);
    Settings::resolveScopesUsing(fn ($subject) => $subject instanceof User ? [$subject, $company] : [$subject]);
    Settings::set('frequency', 'weekly');
    Settings::for($company)->set('frequency', 'daily');
    Settings::feature('tax_feature')->for($user)->enable();
    Settings::groups()->create(['name' => 'Billing']);

    assertMatchesSchema($spec, 'Overview', $this->getJson('/api/setanjo/overview')->json('data'));
    assertMatchesSchema($spec, 'Setting', $this->getJson('/api/setanjo/settings')->json('data.0'));
    assertMatchesSchema($spec, 'Feature', $this->getJson('/api/setanjo/features')->json('data.0'));
    assertMatchesSchema($spec, 'Group', $this->getJson('/api/setanjo/groups')->json('data.0'));
    assertMatchesSchema($spec, 'ScopeType', $this->getJson('/api/setanjo/scopes')->json('data.0'));
    assertMatchesSchema($spec, 'RecordWithCounts', $this->getJson('/api/setanjo/scopes/user')->json('data.0'));
    assertMatchesSchema($spec, 'PageMeta', $this->getJson('/api/setanjo/scopes/user')->json('meta'));
    assertMatchesSchema($spec, 'Record', $this->getJson('/api/setanjo/scopes/user?search=Jane')->json('data.0'));

    $scoped = $this->getJson("/api/setanjo/settings/frequency?scope_type=user&scope_id={$user->id}")->json('data');
    assertMatchesSchema($spec, 'ScopedSetting', $scoped);
    assertMatchesSchema($spec, 'ChainLevel', $scoped['chain'][0]);
    assertMatchesSchema($spec, 'ScopedFeature', $this->getJson("/api/setanjo/features/tax_feature?scope_type=user&scope_id={$user->id}")->json('data'));
    assertMatchesSchema($spec, 'KeyOverride', $this->getJson('/api/setanjo/settings/frequency/overrides')->json('data.0'));
    assertMatchesSchema($spec, 'DriftReport', $this->getJson('/api/setanjo/drift')->json('data'));
    assertMatchesSchema($spec, 'HistoryEntry', $this->getJson('/api/setanjo/history')->json('data.0'));

    $bulk = $this->postJson('/api/setanjo/features/tax_feature/overrides', ['scope_type' => 'user', 'ids' => [(string) $user->id], 'state' => false, 'dry_run' => true])->json('data');
    assertMatchesSchema($spec, 'BulkResult', $bulk);
});

it('always sends meta as an object', function () {
    $content = $this->getJson('/api/setanjo/settings/company_name')->assertOk()->getContent();

    expect($content)->toContain('"meta":{}')
        ->and($content)->toContain('"definition_meta":{}')
        ->not->toContain('"meta":[]');
});

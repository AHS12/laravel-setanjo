# Laravel 13 Upgrade Plan

Modernize `ahs12/laravel-setanjo` to support **Laravel 13** and current PHP, aligning with the
`spatie/package-skeleton-laravel` toolchain. Legacy users (Laravel 10/11, PHP 8.2) remain on the
last release of the old line.

## Goal

- Add support for Laravel 13.x and keep Laravel 12.x.
- Raise the PHP floor to a currently supported version.
- Upgrade the dev/test toolchain (Testbench, Pest, Larastan, PHPStan) so the matrix can actually
  test Laravel 13.
- No public API / behavior changes.

## Support Matrix

| | Before | After |
|---|---|---|
| PHP | `^8.2` | `^8.4` (drops 8.2 and 8.3) |
| Laravel | 10, 11, 12 | **12, 13** |
| Testbench | 8, 9, 10 | **10, 11** |
| Pest | 2, 3 | **4** |
| Larastan | 2, 3 | **3** |
| PHPStan | 1, 2 | **2** |

Rationale:
- Laravel 10 is EOL; Laravel 11 security support ended 2026-03-12. Dropped.
- PHP `^8.4` matches the current `spatie/package-skeleton-laravel` default and keeps the toolchain
  (Pest 4 requires PHP 8.3+; 8.4 is the recommended runtime for Laravel 13).
- Legacy users on Laravel 10/11 or PHP 8.2/8.3 stay on the previous release line (see the
  compatibility matrix below).
- Testbench 11 is the only harness matching Laravel 13; it needs PHPUnit 11/12 → Pest 4.

### Version Compatibility Matrix

This must be documented in `README.md` so users pick the right release:

| Package version | Laravel | PHP | Testbench | Status |
|---|---|---|---|---|
| `1.x` (current line) | 10, 11, 12 | `^8.2` | 8, 9, 10 | Maintenance / legacy |
| `2.x` (this release) | 12, 13 | `^8.4` | 10, 11 | Active |

## Step 1 — `composer.json`

Update `require`:

```json
"require": {
    "php": "^8.4",
    "spatie/laravel-package-tools": "^1.16",
    "illuminate/contracts": "^12.0||^13.0"
},
```

Update `require-dev`:

```json
"require-dev": {
    "laravel/pint": "^1.14",
    "nunomaduro/collision": "^8.8",
    "larastan/larastan": "^3.0",
    "orchestra/testbench": "^11.0.0||^10.0.0",
    "pestphp/pest": "^4.0",
    "pestphp/pest-plugin-arch": "^4.0",
    "pestphp/pest-plugin-laravel": "^4.0",
    "phpstan/extension-installer": "^1.4",
    "phpstan/phpstan-deprecation-rules": "^2.0",
    "phpstan/phpstan-phpunit": "^2.0",
    "spatie/laravel-ray": "^1.35"
},
```

Everything else (`autoload`, `extra`, `scripts`, `config`, `minimum-stability`) stays as-is.

## Step 2 — `.github/workflows/run-tests.yml`

Replace the matrix with Laravel 12/13 and the matching Testbench versions:

```yaml
matrix:
  os: [ubuntu-latest, windows-latest]
  php: [8.5, 8.4]
  laravel: [13.*, 12.*]
  stability: [prefer-lowest, prefer-stable]
  include:
    - laravel: 13.*
      testbench: 11.*
    - laravel: 12.*
      testbench: 10.*
```

Optional while here:
- Add a `pull_request` trigger (currently push-only).
- Bump `actions/checkout` to the current major (skeleton uses `@v7`).

## Step 3 — `.github/workflows/phpstan.yml`

Bump the analysis PHP version to match the floor:

```yaml
php-version: '8.4'
```

## Step 4 — Source audit (`src/`)

Reviewed every file. **No breaking changes are required for Laravel 13.**

- `SetanjoServiceProvider` — extends `Spatie\...\PackageServiceProvider`, current API. OK.
- `Models/Setting.php` — uses the `$casts` property and legacy `getValueAttribute`/`setValueAttribute`
  accessors. Still supported in Laravel 13. Optional modernization to `casts()` and `Attribute`
  casts (Laravel 11+), but not required and out of scope.
- `SetanjoManager`, `DatabaseSettingsRepository`, `SettingType`, `HasSettings`, commands, facade,
  contracts, exceptions — no version-sensitive APIs.

Recommended follow-ups (separate PRs, not part of this upgrade):
- Consider requiring `illuminate/database` + `illuminate/support` (not just `illuminate/contracts`),
  since `src/` references Eloquent and `Illuminate\Support\Collection`/`Cache`.
- Replace the mutable `value` accessor with a proper `Attribute`/custom cast.

## Step 5 — Tests audit (`tests/`)

Existing Pest tests should run unchanged under Pest 4. Verify the following after upgrading:

- `tests/ArchTest.php` asserts against namespaces that may not exist yet
  (`Ahs12\Setanjo\ValueObjects`, `Ahs12\Setanjo\Config`). Pest 4 / pest-plugin-arch 4 can be stricter;
  if these fail, either create the namespaces or remove/relax those assertions.
- `tests/TestCase.php` creates schema manually with `nullableMorphs` — unaffected.
- `tests/Pest.php` binding via `uses(TestCase::class)->in(__DIR__)` is still valid in Pest 4.

## Step 6 — Docs & release metadata

- `README.md`: update the Requirements/Compatibility section and add the **Version Compatibility
  Matrix** from the section above (PHP 8.4+, Laravel 12/13 for the new line; legacy mapping for the
  old line), plus any `composer require` version hints.
- `CHANGELOG.md`: add a `## 2.0.0 - <date>` entry noting the breaking support-matrix change (drops
  Laravel 10/11 and PHP 8.2/8.3) and the toolchain upgrade (Testbench 11, Pest 4, Larastan 3).
- Version bump: current release is `1.0.0`; this is a **breaking** drop of old platform support →
  release as **`2.0.0`** (next major). Users on the legacy line keep `^1.0`; `2.x` is offered only
  to Laravel 12/13 + PHP 8.4 users. Tag and let the existing changelog workflow run.

## Verification Checklist

Run locally (PHP 8.4 is installed here):

```powershell
composer update
composer test          # vendor/bin/pest
composer analyse       # vendor/bin/phpstan analyse
composer format        # vendor/bin/pint
```

Then confirm:

- [ ] `composer update` resolves `laravel/framework:^13.0` + `orchestra/testbench:^11.0`.
- [ ] Full test suite green.
- [ ] PHPStan passes (regenerate the baseline if Larastan 3 reports new/different errors:
      `vendor/bin/phpstan analyse --generate-baseline`).
- [ ] CI matrix green on all PHP/Laravel/OS combinations.
- [ ] A fresh Laravel 13 app can `composer require ahs12/laravel-setanjo` and run
      `php artisan setanjo:install-defaults` without error.

## Risks & Rollback

- **Pest 4 migration** is the most likely source of friction (stricter arch expectations, PHPUnit 12).
- **PHPStan/Larastan 3** may surface new findings; the baseline handles most of this.
- Rollback is simply reverting this branch; the previous tagged release continues to serve
  Laravel 10/11 + PHP 8.2 users.

## Out of Scope

- New features.
- Modernizing model casts/accessors.
- Changing the migration stub, config, or public API.

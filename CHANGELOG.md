# Changelog

All notable changes to `laravel-setanjo` will be documented in this file.

## 3.0.0 - 2026-11-15

### What's Changed

##### Highlights

v3 is additive: the 2.x API (`Settings::get/set/has/forget/all/flush/for/forTenantId`, `HasSettings`) keeps working and existing rows are read unchanged. Run the four new migrations; definitions and feature-flag tables can be adopted later. See [UPGRADE.md](https://github.com/AHS12/laravel-setanjo/blob/main/UPGRADE.md).

##### What's New

* **Setting definitions:** declare settings in code (`SettingDefinition` + `DefinesSetting`, usually on an enum) with type, default, label, group, validation rules and allowed scopes. `set()` validates and coerces to the declared type; `get()` falls back to the definition default.
* **Meta:** free-form JSON per setting (`unit`, `min`/`max`, `options`, ...) with a configurable size cap.
* **Groups:** ordered setting groups with assign/reorder helpers; works with an app's existing groups table via `groups_table`.
* **Feature flags:** dedicated `setanjo_features` / `setanjo_feature_overrides` tables with a global state plus per-user/tenant/branch overrides. Includes `setanjo.feature` middleware, `@feature` Blade directive, opt-in Gate hook and `HasFeatures` trait.
* **Layered resolution:** user → tenant → global → default, loaded in one query; `explain()` / `effective()` show which layer a value came from.
* **Events & history:** `SettingUpdated`, `SettingForgotten`, `SettingMetaUpdated`, `FeatureToggled`, `SettingGroupChanged` and more; optional history table that masks sensitive values.
* **Commands:** `setanjo:sync` (`--dry-run`, `--prune`, `--force-types`), `setanjo:export` / `setanjo:import`, `setanjo:migrate-feature-flags`.

##### Fixed

* `has()` returned `false` for a stored `null`.
* Changing a key's type (e.g. int → array) threw "Array to string conversion".
* Queue workers and Octane kept loaded values for the whole process; they are now reset before every job and request.
* Cache flushing now works on every cache driver (versioned keys, no tags).
* `setanjo:clear-cache` returns an exit code instead of calling `exit(1)`.
* Migration rollback keeps an app's own pre-existing `group_id` column.

##### Behaviour Changes

* `has()` on a stored `null` now returns `true`.
* `get()` on a defined key with no stored value returns the definition default.
* `Settings::set()` throws for keys defined as features.
* Cache keys are versioned (`{prefix}:v{n}:settings:{scope}`); hand-built key clearing no longer works — use `Settings::forgetCache()` / `flushCache()`.
* The repository is now a singleton shared with the manager.
* `SettingsRepositoryInterface` has new methods; custom repositories must implement them (or extend `DatabaseSettingsRepository`).

##### Deprecated

* `setanjo:install-defaults` in favour of `setanjo:sync`.

##### Internal

* Test suite runs against MySQL 8.4 and Postgres 17 in CI (`DB_DRIVER`), in addition to SQLite.
* Update CHANGELOG for 2.0.0 by @github-actions[bot] in https://github.com/AHS12/laravel-setanjo/pull/21
* Update CHANGELOG for 2.0.0 by @github-actions[bot] in https://github.com/AHS12/laravel-setanjo/pull/21
* RC 3.0.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/22

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/2.0.0...3.0.0

## 2.0.0 - 2026-09-23

### What's Changed

### 2.0.0 - 2026-09-23

#### Breaking Changes

* Dropped Laravel 10 and 11 support; the package now supports Laravel 12 and 13.
* Dropped PHP 8.2 and 8.3 support; the minimum PHP version is now 8.4.
* Legacy users should stay on the `1.x` line (`composer require "ahs12/laravel-setanjo:^1.0"`).

#### What's Changed

* Added Laravel 13 support (`illuminate/contracts: ^12.0||^13.0`).
  
* Upgraded the test toolchain: Testbench `^11.0||^10.0`, Pest `^4.0`, Larastan `^3.0`.
  
* Updated the CI matrix to PHP 8.5/8.4 and Laravel 13/12.
  
* Update CHANGELOG for 1.0.0 by @github-actions[bot] in https://github.com/AHS12/laravel-setanjo/pull/10
  
* chore(deps): bump peter-evans/create-pull-request from 6 to 7 by @dependabot[bot] in https://github.com/AHS12/laravel-setanjo/pull/11
  
* chore(deps): bump stefanzweifel/git-auto-commit-action from 5 to 6 by @dependabot[bot] in https://github.com/AHS12/laravel-setanjo/pull/12
  
* RC-2.0.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/19
  

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/AHS12/laravel-setanjo/pull/11

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/1.0.0...2.0.0

## 1.0.0 - 2025-06-14

### What's Changed

* Update CHANGELOG for 0.0.3 by @github-actions in https://github.com/AHS12/laravel-setanjo/pull/6
* Release Candidate for V1.0.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/8

### New Contributors

* @github-actions made their first contribution in https://github.com/AHS12/laravel-setanjo/pull/6

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/0.0.3...1.0.0

## 0.0.3 - 2025-06-12

### What's Changed

* workflow: update changelog action to create pull requests instead of direct commits by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/4

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/0.0.2...0.0.3

## 0.0.2 - 2025-06-12

### What's Changed

* docs: update README improvement by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/2

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/0.0.1...0.0.2

## 0.0.1 - 2025-06-12

### What's Changed

* Release Candidate 1 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/1

### New Contributors

* @AHS12 made their first contribution in https://github.com/AHS12/laravel-setanjo/pull/1

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/commits/0.0.1

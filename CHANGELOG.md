# Changelog

All notable changes to `laravel-setanjo` will be documented in this file.

## 3.1.0 - 2026-11-16

### What's Changed

##### Highlights

3.1 adds a **dashboard** for settings, feature flags and per-record overrides, and the same actions as an **HTTP API** for your own admin UI, scripts or services. No migrations and no code changes are needed: the dashboard only opens in `local` until you allow it, and the API is off until you turn it on. See [UPGRADE.md](https://github.com/AHS12/laravel-setanjo/blob/main/UPGRADE.md).

##### What's New

* **Dashboard at `/setanjo`:** nothing to install or publish (no Livewire, Vue or Node in your app). The page carries its own prebuilt assets, so `composer update` never leaves stale assets behind.
  * **Screens:** Overview, Settings, Feature flags, Overrides, Groups, Drift, History, Import / export.
  * **Detail panel for any setting or flag:** its value, every record that overrides it, recent changes with Revert, and copyable code (`Settings::get(...)`, `@feature(...)`, route middleware). Each key has its own link to share (`#/flags/new_checkout`).
  * **Flag targeting:** turn a flag on or off for records of any type by searching, or paste up to 1000 ids, preview what changes, then apply.
  * **Any model can be a scope** (User, Company, Branch…), including UUID/ULID keys, soft-deleted records and records hidden by global scopes. Overrides left behind by deleted records are listed and can be cleared.
  * **Where a value comes from:** each record shows the full lookup chain (`Jane: not set › Acme: "daily" › Global: "weekly"`) and which level wins.
  * **Faster to use:** <kbd>Ctrl</kbd>/<kbd>⌘</kbd> <kbd>K</kbd> searches settings, flags, records and actions; keyboard shortcuts (`?` lists them); Undo on every save, reset and toggle; unsaved edits are marked, and leaving the screen asks first.
  * **History:** grouped by day, readable JSON diffs, and Revert for any change.
  * **Editors:** JSON validation and formatting, a colour picker, units from meta, and a show/hide button on secret fields.
  * **Branding:** set the title, logo and accent colour (`dashboard.brand`) and the "Back to app" link (`dashboard.home`). Light, dark or system theme; top navigation; works on phones.
  
* **Access control (the Telescope / Pulse pattern):** `dashboard.middleware` defaults to `['web', Authorize::class]`. `Authorize` allows the `local` environment, otherwise the `viewSetanjo` gate or `Setanjo::auth()`. Keep it, put your own middleware in front of it, or replace it (`['web', 'auth', 'role:admin']`). Whatever the list:
  * outside `local`, someone must be signed in (`require_auth`)
  * changes need read-only mode off, plus `Setanjo::manage()` or the `manageSetanjo` gate when you define them
  
* **HTTP API (off by default):** `SETANJO_API_ENABLED=true` serves the dashboard's 30 endpoints under `api/setanjo`, with their own middleware (e.g. `['api', 'auth:sanctum', Authorize::class]`).
  * No session or CSRF token needed, and every answer is JSON, errors included.
  * Works with the dashboard turned off; the same read-only and manage rules apply.
  * Covers settings and flags (globally or as one record sees them), per-key overrides, bulk flag targeting, groups, records, drift and sync, history with revert, and import/export.
  * Every endpoint, with examples: [docs/API.md](https://github.com/AHS12/laravel-setanjo/blob/main/docs/API.md).
  

##### Fixed

* On Postgres, reading or opening a UUID/ULID-keyed record no longer fails with `invalid input syntax for type bigint` when the override id columns are the default integers. Those lookups are now skipped, since such ids can't be stored there anyway.

##### Behaviour Changes

* With `history.enabled`, saving a value, meta or flag state that's already stored no longer writes a history row.
* `FeatureToggled` has a new optional `previous` argument, and flag history rows now store the state before the change in `old_value` (`'true'`, `'false'` or `'inherit'`). Flag rows written before 3.1 keep `null` there and can't be reverted.

##### Internal

* CI checks that the built dashboard assets match their sources.
* `testbench.yaml` registers the package provider, so Larastan resolves the `setanjo::` views.
* RC 3.1.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/25
* OpenAPI 3.1 spec for the HTTP API by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/26
* OpenAPI spec for 3.1.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/27

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/3.0.0...3.1.0

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

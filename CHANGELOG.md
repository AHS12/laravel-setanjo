# Changelog

All notable changes to `laravel-setanjo` will be documented in this file.

## 3.2.0 - 2026-11-16

### What's Changed

##### Highlights

3.2 adds **rules to feature flags**: turn a flag on and off on a schedule, roll it out to a percentage of records, make it depend on other flags, and archive flags you no longer use. The dashboard gets controls for all of them, plus new dropdowns and a date picker. One migration is needed, and it only adds empty columns: every existing flag keeps working as before. See [UPGRADE.md](https://github.com/AHS12/laravel-setanjo/blob/main/UPGRADE.md).

##### What's New

* **Flag rules.** Rules only turn an **on** flag off for some records. A flag is decided in this order:
  
  1. an archived flag is off everywhere
  2. a record's own override wins
  3. otherwise the global state (or default) must be on
  4. then the schedule, required flags and rollout must all allow it
  
  ```php
  Settings::feature('new_checkout')
      ->enable()
      ->schedule('2026-12-01 09:00', null)  // on from / off from (either may be null)
      ->rollout(25)                         // 25% of records; null for everyone
      ->requires(['new_cart']);             // off unless new_cart is on too
  
  Settings::feature('old_flow')->archive(); // ->restore()
  
  ```
* v3.2: flag rules (schedule, rollout, requires, archive) and dashboard controls by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/30
  
* Fix: read flags before the 3.2 migration has run by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/31
  
* RC 3.2.0 by @AHS12 in https://github.com/AHS12/laravel-setanjo/pull/32
  

**Full Changelog**: https://github.com/AHS12/laravel-setanjo/compare/3.1.0...3.2.0

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

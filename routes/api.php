<?php

/*
 * The JSON API. Loaded twice: under the dashboard ({path}/api, the dashboard's middleware) and,
 * when `setanjo.api.enabled`, as the public HTTP API ({api.prefix}, the API's middleware).
 * See docs/API.md.
 */

use Ahs12\Setanjo\Http\Controllers\Dashboard\DriftController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\FeaturesController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\GroupsController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\HistoryController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\OverviewController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\ScopesController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\SettingsController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\TransferController;
use Illuminate\Support\Facades\Route;

// Setting keys may contain dots, dashes and colons: allow anything but a slash.
$key = '[^/]+';

Route::get('overview', OverviewController::class)->name('overview');

Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('settings/order', [SettingsController::class, 'order'])->name('settings.order');
Route::get('settings/{key}/overrides', [SettingsController::class, 'overrides'])->where('key', $key)->name('settings.overrides');
Route::get('settings/{key}', [SettingsController::class, 'show'])->where('key', $key)->name('settings.show');
Route::put('settings/{key}', [SettingsController::class, 'update'])->where('key', $key)->name('settings.update');
Route::delete('settings/{key}', [SettingsController::class, 'destroy'])->where('key', $key)->name('settings.destroy');
Route::put('settings/{key}/meta', [SettingsController::class, 'meta'])->where('key', $key)->name('settings.meta');

Route::get('features', [FeaturesController::class, 'index'])->name('features.index');
Route::post('features/order', [FeaturesController::class, 'order'])->name('features.order');
Route::get('features/{key}/overrides', [FeaturesController::class, 'overrides'])->where('key', $key)->name('features.overrides');
Route::post('features/{key}/overrides', [FeaturesController::class, 'bulk'])->where('key', $key)->name('features.bulk');
Route::get('features/{key}', [FeaturesController::class, 'show'])->where('key', $key)->name('features.show');
Route::put('features/{key}', [FeaturesController::class, 'update'])->where('key', $key)->name('features.update');
Route::put('features/{key}/meta', [FeaturesController::class, 'meta'])->where('key', $key)->name('features.meta');

Route::get('groups', [GroupsController::class, 'index'])->name('groups.index');
Route::post('groups', [GroupsController::class, 'store'])->name('groups.store');
Route::post('groups/order', [GroupsController::class, 'order'])->name('groups.order');
Route::put('groups/{group}', [GroupsController::class, 'update'])->whereNumber('group')->name('groups.update');
Route::delete('groups/{group}', [GroupsController::class, 'destroy'])->whereNumber('group')->name('groups.destroy');

Route::get('scopes', [ScopesController::class, 'index'])->name('scopes.index');
Route::get('scopes/{type}', [ScopesController::class, 'records'])->name('scopes.records');
Route::get('scopes/{type}/{id}', [ScopesController::class, 'show'])->name('scopes.show');
Route::delete('scopes/{type}/{id}', [ScopesController::class, 'destroy'])->name('scopes.destroy');

Route::get('drift', [DriftController::class, 'index'])->name('drift.index');
Route::post('sync', [DriftController::class, 'sync'])->name('sync');

Route::get('history', [HistoryController::class, 'index'])->name('history.index');
Route::post('history/{history}/revert', [HistoryController::class, 'revert'])->whereNumber('history')->name('history.revert');

Route::get('export', [TransferController::class, 'export'])->name('export');
Route::post('import', [TransferController::class, 'import'])->name('import');

<?php

use Ahs12\Setanjo\Http\Controllers\Dashboard\DashboardController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\DriftController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\FeaturesController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\GroupsController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\HistoryController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\ScopesController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\SettingsController;
use Ahs12\Setanjo\Http\Controllers\Dashboard\TransferController;
use Illuminate\Support\Facades\Route;

// Setting keys may contain dots, dashes and colons: allow anything but a slash.
$key = '[^/]+';

Route::prefix('api')->name('api.')->group(function () use ($key) {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/order', [SettingsController::class, 'order'])->name('settings.order');
    Route::put('settings/{key}', [SettingsController::class, 'update'])->where('key', $key)->name('settings.update');
    Route::delete('settings/{key}', [SettingsController::class, 'destroy'])->where('key', $key)->name('settings.destroy');
    Route::put('settings/{key}/meta', [SettingsController::class, 'meta'])->where('key', $key)->name('settings.meta');

    Route::get('features', [FeaturesController::class, 'index'])->name('features.index');
    Route::post('features/order', [FeaturesController::class, 'order'])->name('features.order');
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

    Route::get('drift', [DriftController::class, 'index'])->name('drift.index');
    Route::post('sync', [DriftController::class, 'sync'])->name('sync');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('export', [TransferController::class, 'export'])->name('export');
    Route::post('import', [TransferController::class, 'import'])->name('import');
});

Route::get('/{view?}', DashboardController::class)->where('view', '^(?!api(/|$)).*')->name('dashboard');

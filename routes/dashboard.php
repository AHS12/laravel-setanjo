<?php

use Ahs12\Setanjo\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

// The dashboard's own JSON API (same endpoints as the public API, behind the dashboard's middleware).
Route::prefix('api')->name('api.')->group(__DIR__.'/api.php');

Route::get('/{view?}', DashboardController::class)->where('view', '^(?!api(/|$)).*')->name('dashboard');

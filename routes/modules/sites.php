<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-sites'])
    ->controller(SiteController::class)
    ->prefix('sites')
    ->name('sites.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-sites')->name('store');
        Route::put('{site}', 'update')->middleware('permission:edit-sites')->name('update');
        Route::delete('{site}', 'destroy')->middleware('permission:delete-sites')->name('destroy');
        Route::post('{site}/areas', 'storeArea')->middleware('permission:edit-sites')->name('areas.store');
        Route::delete('{site}/areas/{area}', 'destroyArea')->middleware('permission:edit-sites')->name('areas.destroy');
    });

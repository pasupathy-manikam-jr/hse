<?php

use App\Http\Controllers\ObservationController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('report', [ObservationController::class, 'create'])->middleware('permission:create-observations')->name('observations.create');
    Route::post('report', [ObservationController::class, 'store'])->middleware('permission:create-observations')->name('observations.store');
    Route::get('photos/{photo}', PhotoController::class)->name('photos.show');

    Route::middleware('permission:manage-observations')
        ->controller(ObservationController::class)
        ->prefix('observations')
        ->name('observations.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{observation}', 'show')->name('show');
            Route::put('{observation}/close', 'close')->middleware('permission:edit-observations')->name('close');
            Route::post('{observation}/actions', 'storeAction')->middleware('permission:create-actions')->name('actions.store');
            Route::delete('{observation}', 'destroy')->middleware('permission:delete-observations')->name('destroy');
        });
});

<?php

use App\Http\Controllers\ChemicalController;
use App\Http\Controllers\EnvironmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:manage-environment')->group(function () {
        Route::get('environment', [EnvironmentController::class, 'index'])->name('environment.index');
        Route::post('environment', [EnvironmentController::class, 'store'])->middleware('permission:edit-environment')->name('environment.store');
    });

    Route::middleware('permission:manage-chemicals')
        ->controller(ChemicalController::class)
        ->prefix('chemicals')
        ->name('chemicals.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:create-chemicals')->name('store');
            Route::post('{chemical}', 'update')->middleware('permission:edit-chemicals')->name('update');
            Route::delete('{chemical}', 'destroy')->middleware('permission:delete-chemicals')->name('destroy');
            Route::get('{chemical}/sds', 'sds')->name('sds');
        });
});

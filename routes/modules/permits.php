<?php

use App\Http\Controllers\PermitController;
use App\Http\Controllers\ShiftHandoverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-permits'])
    ->controller(PermitController::class)
    ->prefix('permits')
    ->name('permits.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->middleware('permission:create-permits')->name('create');
        Route::post('/', 'store')->middleware('permission:create-permits')->name('store');
        Route::get('{permit}', 'show')->name('show');
        Route::put('{permit}', 'update')->middleware('permission:create-permits')->name('update');
        Route::delete('{permit}', 'destroy')->middleware('permission:delete-permits')->name('destroy');
        Route::put('{permit}/precautions', 'precautions')->middleware('permission:create-permits')->name('precautions');
        Route::post('{permit}/gas-tests', 'storeGasTest')->middleware('permission:create-permits')->name('gas-tests.store');
        Route::post('{permit}/isolations', 'storeIsolation')->middleware('permission:create-permits')->name('isolations.store');
        Route::put('{permit}/isolations/{isolation}/remove', 'removeIsolation')->middleware('permission:create-permits')->name('isolations.remove');
        // Approving also needs approve-permits (checked in the controller); closing is signed.
        Route::put('{permit}/status', 'transition')->middleware('permission:create-permits')->name('transition');
    });

Route::middleware(['auth', 'verified', 'permission:manage-permits'])
    ->controller(ShiftHandoverController::class)
    ->prefix('handovers')
    ->name('handovers.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-permits')->name('store');
        Route::put('{handover}/acknowledge', 'acknowledge')->name('acknowledge');
    });

<?php

use App\Http\Controllers\ContractorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-contractors'])
    ->controller(ContractorController::class)
    ->prefix('contractors')
    ->name('contractors.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-contractors')->name('store');
        Route::put('{contractor}', 'update')->middleware('permission:edit-contractors')->name('update');
        Route::put('{contractor}/approval', 'toggleApproval')->middleware('permission:approve-contractors')->name('approval');
        Route::delete('{contractor}', 'destroy')->middleware('permission:delete-contractors')->name('destroy');
    });

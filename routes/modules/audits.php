<?php

use App\Http\Controllers\InternalAuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-audits'])
    ->controller(InternalAuditController::class)
    ->prefix('audits')
    ->name('audits.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-audits')->name('store');
        Route::get('{audit}', 'show')->name('show');
        Route::put('{audit}/start', 'start')->middleware('permission:edit-audits')->name('start');
        Route::post('{audit}/findings', 'storeFinding')->middleware('permission:edit-audits')->name('findings.store');
        Route::put('{audit}/complete', 'complete')->middleware('permission:edit-audits')->name('complete');
        Route::post('{audit}/actions', 'storeAction')->middleware('permission:create-actions')->name('actions.store');
    });

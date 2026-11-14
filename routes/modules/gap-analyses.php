<?php

use App\Http\Controllers\GapAnalysisController;
use Illuminate\Support\Facades\Route;

// Part of evaluating the management system (ISO 45001 §9), so it shares the audit permissions.
Route::middleware(['auth', 'verified', 'permission:manage-audits'])
    ->controller(GapAnalysisController::class)
    ->prefix('gap-analyses')
    ->name('gap-analyses.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-audits')->name('store');
        Route::get('{gapAnalysis}', 'show')->name('show');
        Route::put('{gapAnalysis}/ratings', 'rate')->middleware('permission:edit-audits')->name('rate');
        Route::put('{gapAnalysis}/complete', 'complete')->middleware('permission:edit-audits')->name('complete');
        Route::post('{gapAnalysis}/actions', 'storeAction')->middleware('permission:create-actions')->name('actions.store');
    });

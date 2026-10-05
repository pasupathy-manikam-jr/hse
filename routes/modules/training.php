<?php

use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\ToolboxTalkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-competencies'])
    ->controller(CompetencyController::class)
    ->prefix('competencies')
    ->name('competencies.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'storeCompetency')->middleware('permission:edit-competencies')->name('store');
        Route::put('{competency}', 'updateCompetency')->middleware('permission:edit-competencies')->name('update');
        Route::post('records', 'storeRecord')->middleware('permission:edit-competencies')->name('records.store');
        Route::delete('records/{record}', 'destroyRecord')->middleware('permission:edit-competencies')->name('records.destroy');
        Route::get('records/{record}/file', 'file')->name('records.file');
    });

Route::middleware(['auth', 'verified', 'permission:manage-toolbox-talks'])
    ->controller(ToolboxTalkController::class)
    ->prefix('toolbox-talks')
    ->name('toolbox-talks.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-toolbox-talks')->name('store');
        Route::delete('{toolboxTalk}', 'destroy')->middleware('permission:delete-toolbox-talks')->name('destroy');
    });

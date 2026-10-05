<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\InspectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-checklists'])
    ->controller(ChecklistController::class)
    ->prefix('checklists')
    ->name('checklists.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-checklists')->name('store');
        Route::get('{checklist}', 'show')->name('show');
        Route::put('{checklist}', 'update')->middleware('permission:edit-checklists')->name('update');
        Route::delete('{checklist}', 'destroy')->middleware('permission:delete-checklists')->name('destroy');
        Route::post('{checklist}/items', 'storeItem')->middleware('permission:edit-checklists')->name('items.store');
        Route::put('{checklist}/items/{item}', 'updateItem')->middleware('permission:edit-checklists')->name('items.update');
        Route::delete('{checklist}/items/{item}', 'destroyItem')->middleware('permission:edit-checklists')->name('items.destroy');
    });

Route::middleware(['auth', 'verified', 'permission:manage-inspections'])
    ->controller(InspectionController::class)
    ->prefix('inspections')
    ->name('inspections.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-inspections')->name('store');
        Route::get('{inspection}', 'show')->name('show');
        // POST, not PUT: answers carry photo uploads, which PHP only parses on POST.
        Route::post('{inspection}/answers', 'saveAnswers')->middleware('permission:create-inspections')->name('answers');
        Route::post('{inspection}/actions', 'storeAction')->middleware('permission:create-actions')->name('actions.store');
        Route::delete('{inspection}', 'destroy')->middleware('permission:delete-inspections')->name('destroy');
    });

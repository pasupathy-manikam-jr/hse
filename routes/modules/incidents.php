<?php

use App\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-incidents'])
    ->controller(IncidentController::class)
    ->prefix('incidents')
    ->name('incidents.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('log', 'log')->name('log');
        Route::get('create', 'create')->middleware('permission:create-incidents')->name('create');
        Route::post('/', 'store')->middleware('permission:create-incidents')->name('store');
        Route::post('draft', 'draft')->middleware(['permission:create-incidents', 'throttle:20,1'])->name('draft');
        Route::get('{incident}', 'show')->name('show');
        Route::put('{incident}', 'update')->middleware('permission:edit-incidents')->name('update');
        Route::delete('{incident}', 'destroy')->middleware('permission:delete-incidents')->name('destroy');
        Route::post('{incident}/people', 'storePerson')->middleware('permission:edit-incidents')->name('people.store');
        Route::put('{incident}/people/{person}', 'updatePerson')->middleware('permission:edit-incidents')->name('people.update');
        Route::delete('{incident}/people/{person}', 'destroyPerson')->middleware('permission:edit-incidents')->name('people.destroy');
        Route::put('{incident}/investigation', 'investigate')->middleware('permission:edit-incidents')->name('investigate');
        Route::put('{incident}/status', 'transition')->middleware('permission:edit-incidents')->name('transition');
        Route::post('{incident}/actions', 'storeAction')->middleware('permission:create-actions')->name('actions.store');
    });

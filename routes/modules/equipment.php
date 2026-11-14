<?php

use App\Http\Controllers\EquipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-equipment'])
    ->controller(EquipmentController::class)
    ->prefix('equipment')
    ->name('equipment.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-equipment')->name('store');
        Route::post('{equipment}', 'update')->middleware('permission:edit-equipment')->name('update');
        Route::delete('{equipment}', 'destroy')->middleware('permission:delete-equipment')->name('destroy');
        Route::post('{equipment}/inspections', 'storeInspection')->middleware('permission:edit-equipment')->name('inspections.store');
        Route::get('{equipment}/inspections/{inspection}/certificate', 'certificate')->name('certificate');
    });

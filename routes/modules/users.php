<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-users'])
    ->controller(UserController::class)
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-users')->name('store');
        Route::put('{user}', 'update')->middleware('permission:edit-users')->name('update');
        Route::delete('{user}', 'destroy')->middleware('permission:delete-users')->name('destroy');
    });

<?php

use App\Http\Controllers\ActionController;
use Illuminate\Support\Facades\Route;

// Open to every signed-in user: the controller limits the list to their own actions
// unless they hold manage-actions, and only an action's owner can mark it done.
Route::middleware(['auth', 'verified'])
    ->controller(ActionController::class)
    ->prefix('actions')
    ->name('actions.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('{action}/complete', 'complete')->name('complete');
        Route::put('{action}/verify', 'verify')->middleware('permission:verify-actions')->name('verify');
        Route::put('{action}/reject', 'reject')->middleware('permission:verify-actions')->name('reject');
    });

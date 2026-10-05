<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('dashboard/hours', [DashboardController::class, 'storeHours'])->middleware('permission:edit-sites')->name('dashboard.hours');
});

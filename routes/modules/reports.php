<?php

use App\Http\Controllers\WeeklyReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-reports'])->group(function () {
    Route::get('reports/weekly', WeeklyReportController::class)->name('reports.weekly');
});

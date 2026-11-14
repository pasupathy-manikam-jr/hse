<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Every worker can see their own surveillance record (USECHH reg. 27(6)).
    Route::get('my-health', [HealthController::class, 'mine'])->name('health.mine');
    Route::get('health/exams/{exam}/file', [HealthController::class, 'examFile'])->name('health.exams.file');

    Route::middleware('permission:manage-health')
        ->controller(HealthController::class)
        ->prefix('health')
        ->name('health.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:edit-health')->name('store');
            Route::get('{programme}', 'show')->name('show');
            Route::put('{programme}', 'update')->middleware('permission:edit-health')->name('update');
            Route::post('{programme}/members', 'storeMember')->middleware('permission:edit-health')->name('members.store');
            Route::put('{programme}/members/{member}/end', 'endMember')->middleware('permission:edit-health')->name('members.end');
            Route::post('{programme}/exams', 'storeExam')->middleware('permission:edit-health')->name('exams.store');
            Route::put('{programme}/exams/{exam}/informed', 'informed')->middleware('permission:edit-health')->name('exams.informed');
            Route::put('{programme}/exams/{exam}/notified', 'notified')->middleware('permission:edit-health')->name('exams.notified');
            Route::post('{programme}/readings', 'storeReading')->middleware('permission:edit-health')->name('readings.store');
            Route::get('readings/{reading}/file', 'readingFile')->name('readings.file');
        });
});

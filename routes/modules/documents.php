<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Readers (any role) open and acknowledge what they were asked to read.
    Route::get('reading', [DocumentController::class, 'reading'])->name('documents.reading');
    Route::get('document-revisions/{revision}/file', [DocumentController::class, 'file'])->name('documents.revisions.file');
    Route::put('document-revisions/{revision}/acknowledge', [DocumentController::class, 'acknowledge'])->name('documents.revisions.acknowledge');

    Route::middleware('permission:manage-documents')
        ->controller(DocumentController::class)
        ->prefix('documents')
        ->name('documents.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:create-documents')->name('store');
            Route::get('{document}', 'show')->name('show');
            Route::put('{document}', 'update')->middleware('permission:edit-documents')->name('update');
            Route::post('{document}/revisions', 'startRevision')->middleware('permission:edit-documents')->name('revisions.store');
            Route::put('{document}/review', 'confirmReview')->middleware('permission:approve-documents')->name('review');
            // POST, not PUT: it carries the file upload.
            Route::post('{document}/revisions/{revision}', 'saveRevision')->middleware('permission:edit-documents')->name('revisions.save');
            Route::put('{document}/revisions/{revision}/move', 'moveRevision')->middleware('permission:edit-documents')->name('revisions.move');
            Route::put('{document}/revisions/{revision}/readers', 'assignReaders')->middleware('permission:edit-documents')->name('revisions.readers');
        });
});

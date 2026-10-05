<?php

use App\Http\Controllers\RiskAssessmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-risk-assessments'])
    ->controller(RiskAssessmentController::class)
    ->prefix('risk-assessments')
    ->name('risk-assessments.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-risk-assessments')->name('store');
        Route::get('{riskAssessment}', 'show')->name('show');
        Route::put('{riskAssessment}', 'update')->middleware('permission:edit-risk-assessments')->name('update');
        Route::delete('{riskAssessment}', 'destroy')->middleware('permission:delete-risk-assessments')->name('destroy');
        Route::post('{riskAssessment}/hazards', 'storeHazard')->middleware('permission:edit-risk-assessments')->name('hazards.store');
        Route::put('{riskAssessment}/hazards/{hazard}', 'updateHazard')->middleware('permission:edit-risk-assessments')->name('hazards.update');
        Route::delete('{riskAssessment}/hazards/{hazard}', 'destroyHazard')->middleware('permission:edit-risk-assessments')->name('hazards.destroy');
        Route::put('{riskAssessment}/approve', 'approve')->middleware('permission:approve-risk-assessments')->name('approve');
        Route::post('{riskAssessment}/revise', 'revise')->middleware('permission:edit-risk-assessments')->name('revise');
        Route::put('{riskAssessment}/clear-review', 'clearReview')->middleware('permission:approve-risk-assessments')->name('clear-review');
    });

<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompetencyController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\ObservationController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\PermitController;
use App\Http\Controllers\PhotoController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

/*
 * The Android app's API (field work only; desk work stays on the web). Docs: /docs/api.
 * Same permissions and site scoping as the web pages.
 */
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('me/competencies', [CompetencyController::class, 'mine']);
        Route::get('notifications', [AuthController::class, 'notifications']);
        Route::post('notifications/read', [AuthController::class, 'markNotificationsRead']);
        // Signs the app's subscription to its private channel (private-App.Models.User.{id}) on the Reverb hub.
        Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate']);

        Route::get('options', OptionsController::class);
        Route::get('photos/{photo}', PhotoController::class);
        Route::get('equipment/{assetTag}', [EquipmentController::class, 'show']);

        Route::get('observations', [ObservationController::class, 'index'])->middleware('permission:create-observations|manage-observations');
        Route::post('observations', [ObservationController::class, 'store'])->middleware('permission:create-observations');
        Route::get('observations/{observation}', [ObservationController::class, 'show'])->middleware('permission:create-observations|manage-observations');

        Route::get('actions', [ActionController::class, 'index']);
        Route::post('actions/{action}/done', [ActionController::class, 'complete']);
        Route::post('actions/{action}/verify', [ActionController::class, 'verify'])->middleware('permission:verify-actions');
        Route::post('actions/{action}/reject', [ActionController::class, 'reject'])->middleware('permission:verify-actions');

        Route::middleware('permission:manage-inspections')->group(function () {
            Route::get('inspections', [InspectionController::class, 'index']);
            Route::post('inspections', [InspectionController::class, 'store'])->middleware('permission:create-inspections');
            Route::get('inspections/{inspection}', [InspectionController::class, 'show']);
            Route::post('inspections/{inspection}/answers', [InspectionController::class, 'answers'])->middleware('permission:create-inspections');
        });

        Route::middleware('permission:manage-permits')->group(function () {
            Route::get('permits', [PermitController::class, 'index']);
            Route::get('permits/{permit}', [PermitController::class, 'show']);
            Route::post('permits/{permit}/gas-tests', [PermitController::class, 'gasTest'])->middleware('permission:create-permits');
            Route::post('permits/{permit}/isolations', [PermitController::class, 'storeIsolation'])->middleware('permission:create-permits');
            Route::post('permits/{permit}/isolations/{isolation}/remove', [PermitController::class, 'removeIsolation'])->middleware('permission:create-permits');
            Route::post('permits/{permit}/status', [PermitController::class, 'transition'])->middleware('permission:create-permits');
        });
    });
});

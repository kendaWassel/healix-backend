<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CareProvider\NurseController;
use App\Http\Controllers\Api\CareProvider\PhysiotherapistController;
use App\Http\Controllers\Api\HomeVisitController;

Route::middleware(['auth:sanctum', 'verified', 'role:care_provider'])
    ->group(function () {

        Route::prefix('provider/nurse')->group(function () {
            Route::get('/profile', [NurseController::class, 'getProfile']);
            // PUT is kept for JSON-only clients; POST is required for multipart
            // file uploads (image/license_file) — PHP never populates
            // $_FILES/$_POST for multipart bodies on PUT requests.
            Route::match(['put', 'post'], '/profile', [NurseController::class, 'updateProfile']);
            Route::get('/orders', [NurseController::class, 'orders']);
            Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);
            Route::get('/schedules', [NurseController::class, 'schedules']);
            Route::post('/schedules/{id}/start-session', [NurseController::class, 'startSession']);
            Route::post('/schedules/{id}/end-session', [NurseController::class, 'endSession']);

            Route::get('/nearby-requests', [NurseController::class, 'nearbyRequests']);

        });

        Route::prefix('provider/physiotherapist')->group(function () {
            Route::get('/profile', [PhysiotherapistController::class, 'getProfile']);
            // PUT is kept for JSON-only clients; POST is required for multipart
            // file uploads (image/license_file) — PHP never populates
            // $_FILES/$_POST for multipart bodies on PUT requests.
            Route::match(['put', 'post'], '/profile', [PhysiotherapistController::class, 'updateProfile']);
            Route::get('/orders', [PhysiotherapistController::class, 'orders']);
            Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
            Route::get('/schedules', [PhysiotherapistController::class, 'schedules']);
            Route::post('/schedules/{id}/start-session', [PhysiotherapistController::class, 'startSession']);
            Route::post('/schedules/{id}/end-session', [PhysiotherapistController::class, 'endSession']);

            Route::get('/nearby-requests', [PhysiotherapistController::class, 'nearbyRequests']);

        });

        Route::post('/home-visits/{visit_id}/follow-up', [HomeVisitController::class, 'createFollowUpHomeVisit']);        
    });
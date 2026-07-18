<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PharmacistController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\Pharmacist\PrescriptionSafetyController;

Route::middleware(['auth:sanctum', 'verified', 'role:pharmacist'])
    ->prefix('pharmacist')
    ->group(function () {

        Route::get('/profile', [PharmacistController::class, 'getProfile']);
        Route::put('/profile', [PharmacistController::class, 'updateProfile']);

        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PharmacistController::class, 'listPrescriptions']);
            Route::get('/{order_id}', [PharmacistController::class, 'viewPrescription']);
            Route::post('/{prescription_id}/accept', [PharmacistController::class, 'accept']);
            Route::post('/{prescription_id}/reject', [PharmacistController::class, 'reject']);
            // Safety verification is a separate step that must run BEFORE pricing.
            Route::post('/{prescription_id}/verify', [PrescriptionSafetyController::class, 'verify']);
            Route::post('/{id}/add-price', [PharmacistController::class, 'addPrice']);
        });

        Route::get('/my-orders', [PharmacistController::class, 'myOrders']);
        Route::get('/track', [PharmacistController::class, 'trackOrders']);
        Route::get('/{orderId}/track', [PharmacistController::class, 'trackOrder']);
        Route::get('/history', [PharmacistController::class, 'ordersHistory']);

        Route::post('/orders/{id}/ready', [OrderController::class, 'markReadyForDelivery']);
    });

Route::middleware(['auth:sanctum', 'role:patient'])
    ->prefix('pharmacist')
    ->group(function () {

        Route::get('/pharmacies', [PharmacyController::class, 'getPharmacies']);
        Route::get('/pharmacies/{id}', [PharmacyController::class, 'getPharmacyDetails']);
    });
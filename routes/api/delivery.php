<?php

use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DeliveryLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified', 'role:delivery'])
    ->prefix('delivery')
    ->group(function () {

        Route::get('/profile', [DeliveryController::class, 'getProfile']);
        Route::put('/profile', [DeliveryController::class, 'updateProfile']);

        Route::get('/new-orders', [DeliveryController::class, 'newOrders']);
        Route::post('/new-orders/{order_id}/accept', [DeliveryController::class, 'accept']);

        Route::get('/tasks', [DeliveryController::class, 'tasks']);

        Route::post('/tasks/{task_id}/set-delivery-fee', [DeliveryController::class, 'setDeliveryFee']);

        Route::post('/tasks/{task_id}/reject', [DeliveryController::class, 'reject']);

        Route::put('/tasks/{task_id}/update-status', [DeliveryController::class, 'updateTaskStatus']);

        // DELIVERY TRACKING ROUTES 

        Route::post('/location/update', [DeliveryLocationController::class, 'updateLocation']);
        Route::get('/location/{task_id}', [DeliveryLocationController::class, 'getLocation']);
    });

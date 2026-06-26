<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\ConsultationController;

Route::middleware(['auth:sanctum', 'verified', 'role:doctor'])
    ->prefix('doctor')
    ->group(function () {

        Route::get('/profile', [DoctorController::class, 'getProfile']);
        Route::put('/profile', [DoctorController::class, 'updateProfile']);

        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);

        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);

        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
    });

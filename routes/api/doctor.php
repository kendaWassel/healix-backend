<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Doctor\PrescriptionSafetyController;

Route::middleware(['auth:sanctum', 'verified', 'role:doctor'])
    ->prefix('doctor')
    ->group(function () {

        Route::get('/profile', [DoctorController::class, 'getProfile']);
        Route::put('/profile', [DoctorController::class, 'updateProfile']);

        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);

        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);

        // Decision-support: verify a draft prescription BEFORE creating it (read-only).
        Route::post('/prescriptions/verify', [PrescriptionSafetyController::class, 'verify']);
        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
    });

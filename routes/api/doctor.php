<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\AI\DoctorSummaryController;
use App\Http\Controllers\Api\Doctor\PrescriptionSafetyController;
use App\Http\Controllers\Api\Lab\LabAnalysisController;

Route::middleware(['auth:sanctum', 'verified', 'role:doctor'])
    ->prefix('doctor')
    ->group(function () {

        Route::get('/profile', [DoctorController::class, 'getProfile']);
        Route::match(['put', 'post'], '/profile', [DoctorController::class, 'updateProfile']);

        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);

        // My patients (via Consultation), each with their lab analyses embedded.
        Route::get('/patients/lab-analyses', [LabAnalysisController::class, 'myPatientsLabAnalyses']);

        // The Healix/AI-generated report for one patient this doctor has a
        // real Consultation with — authorized per-request via
        // DoctorSummaryPolicy::view, not just this route's own role gate.
        Route::get('/patients/{patient_id}/doctor-summaries', [DoctorSummaryController::class, 'forPatient']);

        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);

        // Decision-support: verify a draft prescription BEFORE creating it (read-only).
        Route::post('/prescriptions/verify', [PrescriptionSafetyController::class, 'verify']);
        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
    });

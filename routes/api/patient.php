<?php

use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeliveryLocationController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified', 'role:patient'])
    ->prefix('patient')
    ->group(function () {

        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);

        Route::get('/my-schedules', [PatientController::class, 'getPatientScheduledConsultations']);
        Route::get('/care-provider-schedules', [PatientController::class, 'getPatientScheduledCareProviders']);

        Route::get('/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
        Route::get('/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);

        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);

        Route::post('/home-visits/{visit_id}/re-request', [HomeVisitController::class, 'reRequestHomeVisit']);
        Route::post('/home-visits/{visit_id}/request-new-care-provider', [PatientController::class, 'requestNewCareProvider']);

        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);
        // Patient self-edit: chronic diseases, allergies, previous surgeries, current medications only.
        Route::put('/medical-record', [MedicalRecordController::class, 'updateOwnMedicalRecord']);
        Route::put('/medical-record/pregnancy', [MedicalRecordController::class, 'updatePregnancyInfo']);

        Route::post('/consultations/{consultation_id}/rate/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::post('/order/{order_id}/rate/{pharmacist_id}', [RatingController::class, 'ratePharmacy']);
        Route::post('/task/{task_id}/rate/{delivery_id}', [RatingController::class, 'rateDelivery']);
        Route::post('/session/{session_id}/rate/{care_provider_id}', [RatingController::class, 'rateCareProvider']);

        Route::get('/view-prescriptions-with-pricing', [PatientController::class, 'getPrescriptionsWithPricing']);

        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PatientController::class, 'getPatientPrescriptions']);
            Route::get('/{prescription_id}', [PatientController::class, 'getPrescriptionDetails']);
            Route::post('/upload', [PatientController::class, 'uploadPaperPrescription']);
            Route::post('/{prescription_id}/send', [PatientController::class, 'sendPrescriptionToPharmacy']);
        });

        Route::get('/orders/delivery-info', [PatientController::class, 'getDeliveryInfo']);
        Route::get('/orders/{order_id}/delivery-info', [PatientController::class, 'getOrderDeliveryInfo']);
        //tracking 
        Route::get('/delivery/location/{task_id}', [DeliveryLocationController::class, 'getLocation']);

        // ========== PATIENT PROFILE MANAGEMENT ==========
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);


        Route::apiResource('conversations', ConversationController::class)->only([
            'index',
            'store',
            'show',
            'destroy',
        ]);

        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);
        // Separate route, separate AI backend (Healix's LangGraph triage
        // service, not the interview/assessment engine storeMessage()
        // above calls) — see ConversationController::storeHealixMessage()'s
        // own docstring for why this isn't a branch inside storeMessage().
        Route::post('conversations/{conversation}/healix-messages', [ConversationController::class, 'storeHealixMessage']);
    });

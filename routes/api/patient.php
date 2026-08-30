<?php

use App\Http\Controllers\Api\AI\HealixSpeechController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeliveryLocationController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\Patient\PrescriptionSafetyController;
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

        // Unified post-assessment booking screen data (result + specialty +
        // available doctors + slots) — one call for the AssessmentResultScreen.
        Route::get('/assessments/{id}/booking', [AssessmentController::class, 'bookingOptions']);

        Route::post('/home-visits/{visit_id}/request-new-care-provider', [PatientController::class, 'requestNewCareProvider']);

        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);
        // Patient self-edit: everything except the doctor-only clinical fields
        // (diagnosis, treatment_plan — see UpdateMedicalRecordRequest).
        Route::match(['put', 'post'],'/medical-record', [MedicalRecordController::class, 'updateOwnMedicalRecord']);
        Route::put('/medical-record/pregnancy', [MedicalRecordController::class, 'updatePregnancyInfo']);

        Route::post('/consultations/{consultation_id}/rate/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::post('/order/{order_id}/rate/{pharmacist_id}', [RatingController::class, 'ratePharmacy']);
        Route::post('/task/{task_id}/rate/{delivery_id}', [RatingController::class, 'rateDelivery']);
        Route::post('/session/{session_id}/rate/{care_provider_id}', [RatingController::class, 'rateCareProvider']);

        Route::get('/view-prescriptions-with-pricing', [PatientController::class, 'getPrescriptionsWithPricing']);

        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PatientController::class, 'getPatientPrescriptions']);
            // Self-check: verify a draft medication list against the patient's
            // own allergies / pregnancy / chronic conditions (read-only).
            Route::post('/verify', [PrescriptionSafetyController::class, 'verify']);
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
        // Healix's own speech I/O (api/main.py's /speech/transcribe,
        // /speech/synthesize) — see HealixSpeechController's own docstring
        // for why transcribe is conversation-scoped (same 'sendMessage'
        // authorization as healix-messages above) and synthesize is not.
        Route::post('conversations/{conversation}/healix-speech/transcribe', [HealixSpeechController::class, 'transcribe']);
        Route::post('healix-speech/synthesize', [HealixSpeechController::class, 'synthesize']);
    });

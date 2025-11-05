<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\NurseController;
use App\Http\Controllers\Api\PhysiotherapistController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\CareProviderController;



//public APIs (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
});

// Uploads (public for registration before auth)
Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

// Specializations for Registration
Route::get('/specializations', [SpecializationController::class, 'listForRegistration']);



Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [LoginController::class, 'logout']);

    // Specializations for Consultation
    Route::get('/patient/specializations', [SpecializationController::class, 'listForConsultation']);

    // Doctors related
    Route::get('/patient/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
    Route::get('/patient/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);


    // Consultations 
    Route::prefix('patient/consultations')->group(function () {
        Route::post('/book', [ConsultationController::class, 'bookConsultation']);
        Route::get('/myschedules', [ConsultationController::class, 'getPatientScheduledConsultations']);
        Route::post('/{id}/call', [ConsultationController::class, 'startCall']);

    });

    //patients
});
Route::middleware('auth:sanctum')->post('/consultations', [ConsultationController::class, 'store']);
use App\Http\Controllers\Api\AppointmentController;




// Route::middleware('auth:sanctum')->get('/provider/nurse/schedules', [NurseScheduleController::class, 'index']);

 // Route::middleware('auth:sanctum')->get('/provider/nurse/orders', [NurseOrderController::class, 'index']);


/*Route::middleware(['auth:sanctum'])->prefix('care-provider')->group(function () {
    Route::get('/orders', [CareProviderOrderController::class, 'index']);
    Route::post('/orders/{id}/accept', [CareProviderOrderController::class, 'accept']);
    Route::post('/orders/{id}/reject', [CareProviderOrderController::class, 'reject']);
});*/






Route::middleware('auth:sanctum')->prefix('careprovider')->group(function () {
     // ==== Nurse ====
    Route::prefix('nurse')->group(function () {
        Route::get('/schedules', [NurseController::class, 'schedules']);
        Route::get('/orders', [NurseController::class, 'orders']);
        Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);
        
    });


    // ==== Physiotherapist ====
    Route::prefix('physiotherapist')->group(function () {
        Route::get('/schedules', [PhysiotherapistController::class, 'schedules']);
        Route::get('/orders', [PhysiotherapistController::class, 'orders']);
        Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
        
    });
});
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




// Protected APIs (auth required) and verified email

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [LoginController::class, 'logout']);


    // Patient
    Route::prefix('patient')->group(function () {

        // Specializations for Consultation
        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);

        // Doctors related
        Route::get('/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
        Route::get('/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);


        // Consultations
        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);
        Route::get('/consultations/my-schedules', [ConsultationController::class, 'getPatientScheduledConsultations']);
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startCall']);

        // Ratings
        Route::post('ratings/doctors/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::get('ratings/doctors/{doctor_id}', [RatingController::class, 'getMyRatingForDoctor']);
    

    });
    
    // Doctor
    Route::prefix('doctor')->group(function () {
        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);
        
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startCall']);
        Route::get('patients/{patient_id}/medical-record', [DoctorController::class, 'viewPatientMedicalRecord']);

    });



    // Provider Nurse
    Route::prefix('provider/nurse')->group(function () {
        Route::get('/schedules', [NurseController::class, 'schedules']);
        Route::get('/orders', [NurseController::class, 'orders']);
        Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);

    });
    // Provider Physiotherapist
    Route::prefix('provider/physiotherapist')->group(function () {
        Route::get('/schedules', [PhysiotherapistController::class, 'schedules']);
        Route::get('/orders', [PhysiotherapistController::class, 'orders']);
        Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
        
    });

});




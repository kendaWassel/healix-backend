<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NurseController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\PhysiotherapistController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;

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

Route::middleware(['auth:sanctum'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [LoginController::class, 'logout']);


    // Patient
    Route::prefix('patient')->group(function () {

        // Specializations for Consultation
        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);

        
        Route::get('/my-schedules', [PatientController::class, 'getPatientScheduledConsultations']);

        // related to Doctors
        Route::get('/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
        Route::get('/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);

        // Notifications
        // Route::get('/notifications', [PatientController::class, 'getNotifications']);


        // Consultations
        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startCall']);
        Route::post('/consultations/{id}/end', [ConsultationController::class,'endConsultation']);

        // Ratings
        Route::post('ratings/doctors/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::get('ratings/consultations/{consultation_id}', [RatingController::class, 'getMyRatingForConsultation']);

        //Prescription
        
    

    });
    
    // Doctor
    Route::prefix('doctor')->group(function () {
        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startCall']);
        Route::get('/patients/{patient_id}/medical-record', [DoctorController::class, 'viewDetails']);
        Route::post('/consultations/{id}/end', [ConsultationController::class, 'endConsultation']);
        Route::post('/home-visit/request', [DoctorController::class,'requestHomeVisit']);

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




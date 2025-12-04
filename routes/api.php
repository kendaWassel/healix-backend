<?php

use App\Models\HomeVisit;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NurseController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\PhysiotherapistController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Patient\PrescriptionStatusController;
use App\Http\Controllers\Pharmacist\PrescriptionController;


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

        // Notifications (available for all authenticated users)
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);


        // Consultations
        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startConsultation']);
        Route::post('/consultations/{id}/end', [ConsultationController::class,'endConsultation']);

        // Ratings
        Route::post('ratings/doctors/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::get('ratings/consultations/{consultation_id}', [RatingController::class, 'getMyRatingForConsultation']);
        Route::post('pharmacies/{pharmacy_id}/rate', [\App\Http\Controllers\Api\RatingController::class, 'ratePharmacy']);


        //Prescription
       Route::get('/prescriptions/{prescription_id}/status', [PrescriptionStatusController::class, 'show']);
    

    });
    
    // Doctor
    Route::prefix('doctor')->group(function () {
        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);
        Route::post('/consultations/{id}/call', [ConsultationController::class, 'startCall']);
        Route::get('/patients/{patient_id}/view-details', [MedicalRecordController::class, 'viewDetails']);
        Route::put('patients/{patient_id}/medical-record/update', [MedicalRecordController::class,'updateMedicalRecord']);
        Route::post('/consultations/{id}/end', [ConsultationController::class, 'endConsultation']);
        Route::post('/home-visit/request', [HomeVisitController::class,'requestHomeVisit']);

    });
    Route::prefix('pharmacist')->group(function () {
    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::get('/prescriptions/{order_id}', [PrescriptionController::class, 'show']);
    Route::post('/prescriptions/{order_id}/deliver', [PrescriptionController::class, 'deliver']);
    Route::post('/prescriptions/{order_id}/accept', [PrescriptionController::class, 'accept']);
    Route::post('/prescriptions/{order_id}/reject', [PrescriptionController::class, 'reject']);
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




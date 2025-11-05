<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\CareProviderController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\OrdersController;


use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\SpecializationController;
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



Route::middleware('auth')->get('/care-provider/requests', [CareProviderController::class, 'requests']);

// Route::middleware('auth:sanctum')->get('/provider/nurse/schedules', [NurseScheduleController::class, 'index']);

 // Route::middleware('auth:sanctum')->get('/provider/nurse/orders', [NurseOrderController::class, 'index']);


/*Route::middleware(['auth:sanctum'])->prefix('care-provider')->group(function () {
    Route::get('/orders', [CareProviderOrderController::class, 'index']);
    Route::post('/orders/{id}/accept', [CareProviderOrderController::class, 'accept']);
    Route::post('/orders/{id}/reject', [CareProviderOrderController::class, 'reject']);
});*/

Route::middleware('auth:sanctum')->get('/careprovider/schedules', [CareProviderController::class, 'requests']);


Route::middleware('auth:sanctum')->group(function () {
    // عرض الطلبات
    Route::get('/careprovider/orders', [OrdersController::class, 'index']);

    // قبول طلب
    Route::post('/careprovider/orders/{id}/accept', [OrdersController::class, 'accept']);

    // رفض طلب
    Route::post('/careprovider/orders/{id}/reject', [OrdersController::class, 'reject']);
});
    


    // Ratings
    Route::prefix('patient/ratings')->group(function () {
        Route::post('doctors/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::get('doctors/{doctor_id}', [RatingController::class, 'getMyRatingForDoctor']);
    });
    Route::get('doctors/{doctor_id}/ratings', [RatingController::class, 'getDoctorRatings']);

    // Ratings
    Route::prefix('patient/ratings')->group(function () {
        Route::post('doctors/{doctorId}', [RatingController::class, 'rateDoctor']);
        Route::get('doctors/{doctorId}', [RatingController::class, 'getMyRatingForDoctor']);
    });
    
    // Doctor ratings (public)
    Route::get('doctors/{doctorId}/ratings', [RatingController::class, 'getDoctorRatings']);
});

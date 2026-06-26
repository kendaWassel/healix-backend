
<?php

use App\Http\Controllers\{FaqController, FirstAidController};
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\Auth\{RegisterController, VerifyEmailController, LoginController};
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Payment\StripeController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/ai.php';
    require __DIR__ . '/api/care_provider.php';
    require __DIR__ . '/api/delivery.php';
    require __DIR__ . '/api/doctor.php';
    require __DIR__ . '/api/patient.php';
    require __DIR__ . '/api/pharmacist.php';
});

// PUBLIC APIs (No Auth Required) 

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);

    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
});

// Public Information
Route::get('/faqs', [FaqController::class, 'index']);
Route::get('/first-aid', [FirstAidController::class, 'index']);
Route::get('/specializations', [SpecializationController::class, 'listForRegistration']);

// File Uploads (for registration before auth)
Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);
Route::get('/medical-records/attachments/{id}/download', [MedicalRecordController::class, 'downloadAttachment'])->name('medical-record.attachment.download');
Route::get('/uploads/download/{id}', [UploadController::class, 'downloadFile'])->name('download.file');
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);


Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    Route::post('/auth/logout', [LoginController::class, 'logout']);

    // PAYMENTS 
    Route::prefix('payments')->group(function () {
        Route::post('/intent', [StripeController::class, 'createIntent']);
        Route::get('/status/{payment_intent_id}', [StripeController::class, 'status']);
    });

    // NOTIFICATIONS 
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Medical Record (Doctor, Nurse, Physiotherapist)
    Route::prefix('patients')->group(function () {
        Route::get('/{patient_id}/view-details', [MedicalRecordController::class, 'viewDetails']);
        Route::put('/{patient_id}/medical-record/update', [MedicalRecordController::class, 'updateMedicalRecord']);
    });

    // Consultation (Doctor or Patient)
    Route::prefix('consultations')->group(function () {
        Route::post('/{id}/call', [ConsultationController::class, 'startConsultation']);
        Route::post('/{id}/end', [ConsultationController::class, 'endConsultation']);
    });
});

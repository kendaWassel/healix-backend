<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{
    RegisterController,
    VerifyEmailController,
    LoginController
};

use App\Http\Controllers\Api\{
    OrderController,
    HomeVisitController,
    RatingController,
    UploadController,
    PatientController,
    DoctorController,
    DeliveryController,
    PharmacistController,
    PharmacyController,
    ConsultationController,
    MedicalRecordController,
    SpecializationController,

};

use App\Http\Controllers\Api\CareProvider\{
    PhysiotherapistController,
    NurseController
};

use App\Http\Controllers\Api\Admin\AdminController;

/*
|--------------------------------------------------------------------------
| Public APIs
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);

    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
});

Route::get('/faqs', [App\Http\Controllers\FaqController::class, 'index']);
Route::get('/first-aid', [App\Http\Controllers\FirstAidController::class, 'index']);

Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

Route::get('/specializations', [SpecializationController::class, 'listForRegistration']);

/*
|--------------------------------------------------------------------------
| Authenticated (even if not approved yet)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/auth/logout', [LoginController::class, 'logout']);

    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin (email verified + approved)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'verified', 'role:admin'])
    ->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}/attachments', [AdminController::class, 'attachments']);
        Route::patch('/users/{id}/approve', [AdminController::class, 'approveUser']);
        Route::patch('/users/{id}/reject', [AdminController::class, 'rejectUser']);
        Route::put('/users/{id}/edit', [AdminController::class, 'editUser']);
        Route::delete('/users/{id}/delete', [AdminController::class, 'deleteUser']);

        // Service quality monitoring
        Route::get('/services', [AdminController::class, 'services']);
});

/*
|--------------------------------------------------------------------------
| Patient
|--------------------------------------------------------------------------
*/
Route::prefix('patient')
    ->middleware(['auth:sanctum', 'verified', 'role:patient'])
    ->group(function () {

        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);
        Route::get('/my-schedules', [PatientController::class, 'getPatientScheduledConsultations']);

        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);

        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);
});

/*
|--------------------------------------------------------------------------
| Doctor
|--------------------------------------------------------------------------
*/
Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'verified', 'role:doctor'])
    ->group(function () {

        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);
        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);
        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
});

/*
|--------------------------------------------------------------------------
| Pharmacist
|--------------------------------------------------------------------------
*/
Route::prefix('pharmacist')
    ->middleware(['auth:sanctum', 'verified', 'role:pharmacist'])
    ->group(function () {

        Route::get('/pharmacies', [PharmacyController::class, 'getPharmacies']);

        Route::get('/prescriptions', [PharmacistController::class, 'listPrescriptions']);
        Route::get('/prescriptions/{order_id}', [PharmacistController::class, 'viewPrescription']);
        Route::post('/prescriptions/{prescription_id}/accept', [PharmacistController::class, 'accept']);
        Route::post('/prescriptions/{prescription_id}/reject', [PharmacistController::class, 'reject']);

        Route::get('/my-orders', [PharmacistController::class, 'myOrders']);
        Route::post('/orders/{id}/ready', [OrderController::class, 'markReadyForDelivery']);
});

/*
|--------------------------------------------------------------------------
| Care Providers
|--------------------------------------------------------------------------
*/
Route::prefix('provider/nurse')
    ->middleware(['auth:sanctum', 'verified', 'role:nurse'])
    ->group(function () {
        Route::get('/orders', [NurseController::class, 'orders']);
        Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);
});

Route::prefix('provider/physiotherapist')
    ->middleware(['auth:sanctum', 'verified', 'role:physiotherapist'])
    ->group(function () {
        Route::get('/orders', [PhysiotherapistController::class, 'orders']);
        Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
});

/*
|--------------------------------------------------------------------------
| Delivery
|--------------------------------------------------------------------------
*/
Route::prefix('delivery')
    ->middleware(['auth:sanctum', 'verified', 'role:delivery'])
    ->group(function () {

        Route::get('/new-orders', [DeliveryController::class, 'newOrders']);
        Route::post('/new-orders/{order_id}/accept', [DeliveryController::class, 'accept']);

        Route::get('/tasks', [DeliveryController::class, 'tasks']);
        Route::post('/tasks/{task_id}/set-delivery-fee', [DeliveryController::class, 'setDeliveryFee']);
        Route::put('/tasks/{task_id}/update-status', [DeliveryController::class, 'updateTaskStatus']);
});

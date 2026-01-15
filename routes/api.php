
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{RegisterController,VerifyEmailController,LoginController};
use App\Http\Controllers\Api\CareProvider\{PhysiotherapistController, NurseController};
use App\Http\Controllers\Api\{OrderController,HomeVisitController,RatingController,UploadController};
use App\Http\Controllers\Api\{PatientController,DoctorController,DeliveryController,PharmacistController,UserController};
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\{FaqController, FirstAidController};
use App\Http\Controllers\Api\NotificationController;

// ========== PUBLIC APIs (No Auth Required) ==========

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

// ========== PROTECTED APIs (Auth + Verified Email) ==========

Route::get('/medical-records/attachments/{id}/download', [\App\Http\Controllers\Api\MedicalRecordController::class, 'downloadAttachment'])->name('medical-record.attachment.download');
Route::get('/uploads/download/{id}', [UploadController::class, 'downloadFile'])->name('download.file');
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Download medical record attachments (authorized access only)
    
    // File Downloads (protected)

    // ========== AUTHENTICATION ==========
    Route::post('/auth/logout', [LoginController::class, 'logout']);

    // ========== NOTIFICATIONS ==========
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // ========== USER PROFILE MANAGEMENT ==========
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'getProfile']);
        Route::put('/me', [UserController::class, 'updateProfile']);
    });

    // ========== ADMIN ROUTES ==========
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/services', [AdminController::class, 'services']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}/attachments', [AdminController::class, 'attachments']);
        Route::patch('/users/{id}/approve', [AdminController::class, 'approveUser']);
        Route::patch('/users/{id}/reject', [AdminController::class, 'rejectUser']);
        Route::put('/users/{id}/edit', [AdminController::class, 'editUser']);
        Route::delete('/users/{id}/delete', [AdminController::class, 'deleteUser']);
    });

    // ========== PATIENT ROUTES ==========
    Route::middleware(['role:patient'])->prefix('patient')->group(function () {
        // Specializations
        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);

        // Schedules
        Route::get('/my-schedules', [PatientController::class, 'getPatientScheduledConsultations']);
        Route::get('/care-provider-schedules', [PatientController::class, 'getPatientScheduledCareProviders']);

        // Doctors
        Route::get('/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
        Route::get('/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);

        // Consultations
        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);

        // Home Visits
        Route::post('/home-visits/{visit_id}/re-request', [HomeVisitController::class, 'reRequestHomeVisit']);
        Route::post('/home-visits/{visit_id}/request-new-care-provider', [PatientController::class, 'requestNewCareProvider']);

        // Medical Records
        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);

        // Ratings
        Route::post('/consultations/{consultation_id}/rate/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::post('/orders/{order_id}/rate/{pharmacist_id}', [RatingController::class, 'ratePharmacy']);
        Route::post('/tasks/{task_id}/rate/{delivery_id}', [RatingController::class, 'rateDelivery']);
        Route::post('/sessions/{session_id}/rate/{care_provider_id}', [RatingController::class, 'rateCareProvider']);

        // Prescriptions
        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PatientController::class, 'getPatientPrescriptions']);
            Route::get('/{prescription_id}', [PatientController::class, 'getPrescriptionDetails']);
            Route::post('/upload', [PatientController::class, 'uploadPaperPrescription']);
            Route::post('/{prescription_id}/send', [PatientController::class, 'sendPrescriptionToPharmacy']);
            Route::get('/with-pricing', [PatientController::class, 'getPrescriptionsWithPricing']);
        });

        // Orders & Delivery
        Route::get('/orders/delivery-info', [PatientController::class, 'getDeliveryInfo']);
        Route::get('/orders/{order_id}/delivery-info', [PatientController::class, 'getOrderDeliveryInfo']);
    });

    // ========== DOCTOR ROUTES ==========
    Route::prefix('doctor')->group(function () {
        Route::get('/profile', [DoctorController::class, 'getProfile']);
        Route::put('/profile', [DoctorController::class, 'updateProfile']);
        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);
        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);
        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
    });

    // ========== PHARMACIST ROUTES ==========
    Route::middleware(['role:pharmacist'])->prefix('pharmacist')->group(function () {
        Route::get('/profile', [PharmacistController::class, 'getProfile']);
        Route::put('/profile', [PharmacistController::class, 'updateProfile']);
        // Pharmacy Information
        Route::get('/pharmacies', [PharmacyController::class, 'getPharmacies']);
        Route::get('/pharmacies/{id}', [PharmacyController::class, 'getPharmacyDetails']);

        // Prescription Management
        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PharmacistController::class, 'listPrescriptions']);
            Route::get('/{order_id}', [PharmacistController::class, 'viewPrescription']);
            Route::post('/{prescription_id}/accept', [PharmacistController::class, 'accept']);
            Route::post('/{prescription_id}/reject', [PharmacistController::class, 'reject']);
            Route::post('/{id}/add-price', [PharmacistController::class, 'addPrice']);
        });

        // Order Management
        Route::prefix('orders')->group(function () {
            Route::get('/my-orders', [PharmacistController::class, 'myOrders']);
            Route::get('/track', [PharmacistController::class, 'trackOrders']);
            Route::get('/{orderId}/track', [PharmacistController::class, 'trackOrder']);
            Route::get('/history', [PharmacistController::class, 'ordersHistory']);
            Route::post('/{id}/ready', [OrderController::class, 'markReadyForDelivery']);
        });
    });

    // ========== CARE PROVIDER ROUTES ==========
    
    // Nurse
    Route::middleware(['role:care_provider'])->prefix('provider/nurse')->group(function () {
        Route::get('/profile', [NurseController::class, 'getProfile']);
        Route::put('/profile', [NurseController::class, 'updateProfile']);
        Route::get('/orders', [NurseController::class, 'orders']);
        Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);
        Route::get('/schedules', [NurseController::class, 'schedules']);
        Route::post('/schedules/{id}/start-session', [NurseController::class, 'startSession']);
        Route::post('/schedules/{id}/end-session', [NurseController::class, 'endSession']);
    });

    // Physiotherapist
    Route::middleware(['role:care_provider'])->prefix('provider/physiotherapist')->group(function () {
        Route::get('/profile', [PhysiotherapistController::class, 'getProfile']);
        Route::put('/profile', [PhysiotherapistController::class, 'updateProfile']);
        Route::get('/orders', [PhysiotherapistController::class, 'orders']);
        Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
        Route::get('/schedules', [PhysiotherapistController::class, 'schedules']);
        Route::post('/schedules/{id}/start-session', [PhysiotherapistController::class, 'startSession']);
        Route::post('/schedules/{id}/end-session', [PhysiotherapistController::class, 'endSession']);
    });

    // ========== SHARED ROUTES ==========
    
    // Medical Record (Doctor, Nurse, Physiotherapist)
    Route::prefix('patients')->group(function () {
        Route::get('/{patient_id}/view-details', [MedicalRecordController::class, 'viewDetails']);
        Route::put('/{patient_id}/medical-record/update', [MedicalRecordController::class, 'updateMedicalRecord']);
    });

    // Home Visit Follow-up (Care Providers)
    Route::middleware(['role:care_provider',])->post('/home-visits/{visit_id}/follow-up', [HomeVisitController::class, 'createFollowUpHomeVisit']);

    // Consultation (Doctor or Patient)
    Route::prefix('consultations')->group(function () {
        Route::post('/{id}/call', [ConsultationController::class, 'startConsultation']);
        Route::post('/{id}/end', [ConsultationController::class, 'endConsultation']);
    });

    // ========== DELIVERY ROUTES ==========
    Route::middleware(['role:delivery'])->prefix('delivery')->group(function () {
        Route::get('/profile', [DeliveryController::class, 'getProfile']);
        Route::put('/profile', [DeliveryController::class, 'updateProfile']);
        Route::get('/new-orders', [DeliveryController::class, 'newOrders']);
        Route::post('/new-orders/{order_id}/accept', [DeliveryController::class, 'accept']);
        Route::get('/tasks', [DeliveryController::class, 'tasks']);
        Route::post('/tasks/{task_id}/set-delivery-fee', [DeliveryController::class, 'setDeliveryFee']);
        Route::put('/tasks/{task_id}/update-status', [DeliveryController::class, 'updateTaskStatus']);
    });
});

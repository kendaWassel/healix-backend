<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{RegisterController,VerifyEmailController,LoginController};
use App\Http\Controllers\Api\CareProvider\{PhysiotherapistController, NurseController};
use App\Http\Controllers\Api\{OrderController,HomeVisitController,RatingController,UploadController};
use App\Http\Controllers\Api\{PatientController,DoctorController,DeliveryController,PharmacistController};

use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\Admin\AdminController;


//public APIs (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
});
// Faq
Route::get('/faqs', [App\Http\Controllers\FaqController::class, 'index']);
// First Aid
Route::get('/first-aid', [App\Http\Controllers\FirstAidController::class, 'index']);

// Uploads (public for registration before auth)
Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

// Specializations for Registration
Route::get('/specializations', [SpecializationController::class, 'listForRegistration']);


// Protected APIs (auth required) and verified email

Route::middleware(['auth:sanctum'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [LoginController::class, 'logout']);

    // Notifications (available for all authenticated users)
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);

    // Admin
    Route::prefix('admin')->group(function(){
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
	Route::get('/users', [AdminController::class, 'users']);
	Route::get('/users/{id}/attachments', [AdminController::class, 'attachments']);
	Route::patch('/users/{id}/approve', [AdminController::class, 'approveUser']);
	Route::patch('/users/{id}/reject', [AdminController::class, 'rejectUser']);
    });

    // Patient
    Route::prefix('patient')->group(function () {

        // Specializations for Consultation
        Route::get('/specializations', [SpecializationController::class, 'listForConsultation']);

        Route::get('/my-schedules', [PatientController::class, 'getPatientScheduledConsultations']);

        // related to Doctors
        Route::get('/doctors/by-specialization', [DoctorController::class, 'getDoctorsBySpecialization']);
        Route::get('/doctors/{id}/available-slots', [DoctorController::class, 'getAvailableSlots']);


        // Consultations
        Route::post('/consultations/book', [ConsultationController::class, 'bookConsultation']);

        // Schedule with care provider
        Route::get('/care-provider-schedules', [PatientController::class, 'getPatientScheduledCareProviders']);
        Route::post('/home-visits/{visit_id}/re-request', [HomeVisitController::class, 'reRequestHomeVisit']);
        Route::post('/home-visits/{visit_id}/request-new-care-provider', [PatientController::class, 'requestNewCareProvider']);
        // Medical Record
        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);

        // Ratings
        Route::post('consultations/{consultation_id}/rate/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::post('order/{order_id}/rate/{pharmacist_id}', [RatingController::class, 'ratePharmacy']);
        Route::post('task/{task_id}/rate/{delivery_id}', [RatingController::class, 'rateDelivery']);
        Route::post('session/{session_id}/rate/{care_provider_id}', [RatingController::class, 'rateCareProvider']);

        // Prescription (patient)
        Route::prefix('prescriptions')->group(function () {
            Route::get('/', [PatientController::class, 'getPatientPrescriptions']);
            Route::get('/{prescription_id}', [PatientController::class, 'getPrescriptionDetails']);
            Route::post('/upload', [PatientController::class, 'uploadPaperPrescription']);
            Route::post('/{prescription_id}/send', [PatientController::class, 'sendPrescriptionToPharmacy']);
        });


        //get prescriptions with pricing info
        Route::get('/view-prescriptions-with-pricing', [PatientController::class, 'getPrescriptionsWithPricing']);
        // Orders (patient)
        Route::get('/orders/delivery-info', [PatientController::class, 'getDeliveryInfo']);
        Route::get('/orders/{order_id}/delivery-info', [PatientController::class, 'getOrderDeliveryInfo']);
    });

    // Doctor
    Route::prefix('doctor')->group(function () {
        Route::get('/my-schedules', [DoctorController::class, 'getDoctorSchedules']);

        Route::post('/home-visit/request', [HomeVisitController::class, 'requestHomeVisit']);
        // Prescription (doctor)
        Route::post('/prescriptions', [DoctorController::class, 'createPrescription']);
    });  
    
    
    // Consultation (Doctor or Patient)
    Route::prefix('consultations')->group(function(){
        Route::post('/{id}/call', [ConsultationController::class, 'startConsultation']);
        Route::post('/{id}/end', [ConsultationController::class, 'endConsultation']);
    });

    // Pharmacist
    Route::prefix('pharmacist')->group(function () {
        // Pharmacy Management
        Route::get('/pharmacies', [PharmacyController::class, 'getPharmacies']);
        Route::get('/pharmacies/{id}', [PharmacyController::class, 'getPharmacyDetails']);


        // Prescription Management
        Route::get('/prescriptions', [PharmacistController::class, 'listPrescriptions']);
        Route::get('/prescriptions/{order_id}', [PharmacistController::class, 'viewPrescription']);
        Route::post('/prescriptions/{prescription_id}/accept', [PharmacistController::class, 'accept']);
        Route::post('/prescriptions/{prescription_id}/reject', [PharmacistController::class, 'reject']);

        //  Order Management
        Route::get('/my-orders', [PharmacistController::class, 'myOrders']);
        Route::get('/orders/track', [PharmacistController::class, 'trackOrders']);
        Route::get('/orders/{orderId}/track', [PharmacistController::class, 'trackOrder']);
        Route::get('/orders/history', [PharmacistController::class, 'ordersHistory']);
        // Set order as ready
        Route::post('/orders/{id}/ready', [OrderController::class, 'markReadyForDelivery']);




        //add price for medications
        Route::post('/prescriptions/{id}/add-price', [PharmacistController::class, 'addPrice']);
        
    });

    // Medical Record view details or update (Doctor, Nurse, Physiotherapist)
    Route::get('/patients/{patient_id}/view-details',[MedicalRecordController::class,'viewDetails']);
    Route::put('/patients/{patient_id}/medical-record/update', [MedicalRecordController::class, 'updateMedicalRecord']);



    // Provider Nurse
    Route::prefix('provider/nurse')->group(function () {
        Route::get('/orders', [NurseController::class, 'orders']);
        Route::post('/orders/{id}/accept', [NurseController::class, 'accept']);
        Route::get('/schedules', [NurseController::class, 'schedules']);
        Route::post('/schedules/{id}/start-session', [NurseController::class, 'startSession']);
        Route::post('/schedules/{id}/end-session', [NurseController::class, 'endSession']);

    });
    

    // Provider Physiotherapist
    Route::prefix('provider/physiotherapist')->group(function () {
        Route::get('/orders', [PhysiotherapistController::class, 'orders']);
        Route::post('/orders/{id}/accept', [PhysiotherapistController::class, 'accept']);
        Route::get('/schedules', [PhysiotherapistController::class, 'schedules']);
        Route::post('/schedules/{id}/start-session', [PhysiotherapistController::class, 'startSession']);
        Route::post('/schedules/{id}/end-session', [PhysiotherapistController::class, 'endSession']);




    });

    // If care provider booking home visit(after the first one by doctor)
    Route::post('/home-visits/{visit_id}/follow-up', [HomeVisitController::class, 'createFollowUpHomeVisit']);

    // Delivery
    Route::prefix('delivery')->middleware('auth:sanctum')->group(function () {
        // New Orders for Delivery
        Route::get('/new-orders', [DeliveryController::class, 'newOrders']);

        // Accept Order
        Route::post('/new-orders/{order_id}/accept', [DeliveryController::class, 'accept']);

        // Tasks for Delivery
        Route::get('/tasks', [DeliveryController::class, 'tasks']);

        // Set Delivery Fee for Task When Picking Up
        Route::post(
            '/tasks/{task_id}/set-delivery-fee',
            [DeliveryController::class, 'setDeliveryFee']
        );

        // Update Task Status
        Route::put(
            '/tasks/{task_id}/update-status',
            [DeliveryController::class, 'updateTaskStatus']
        );

    });
});

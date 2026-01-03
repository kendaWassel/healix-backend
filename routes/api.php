<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NurseController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\HomeVisitController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\PharmacistController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\MedicalRecordController;
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

    // Notifications (available for all authenticated users)
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);


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

        // Medical Record
        Route::get('/medical-record', [MedicalRecordController::class, 'getPatientMedicalRecord']);

        // Ratings
        Route::post('ratings/doctors/{doctor_id}', [RatingController::class, 'rateDoctor']);
        Route::post('pharmacies/{pharmacy_id}/rate', [RatingController::class, 'ratePharmacy']);
        Route::post('deliveries/{delivery_id}/rate', [RatingController::class, 'rateDelivery']);

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
        Route::get('/orders/status', [PatientController::class, 'getOrdersStatus']);
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
        Route::post('/prescriptions/{order_id}/deliver', [PharmacistController::class, 'complete']);
        Route::post('/prescriptions/{order_id}/accept', [PharmacistController::class, 'accept']);
        Route::post('/prescriptions/{order_id}/reject', [PharmacistController::class, 'reject']);

        //  Order Management
        Route::get('/my-orders', [PharmacistController::class, 'myOrders']);
        Route::get('/orders/track', [PharmacistController::class, 'trackOrders']);
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

    // Delivery
Route::prefix('delivery')->middleware('auth:sanctum')->group(function () {

    // الطلبات الجاهزة للتوصيل
    Route::get('/new-orders', [DeliveryController::class, 'newOrders']);

    // قبول طلب توصيل
    Route::post('/new-orders/{order_id}/accept', [DeliveryController::class, 'accept']);

    // مهام الدليفري
    Route::get('/tasks', [DeliveryController::class, 'tasks']);

    // إدخال سعر التوصيل (قبل delivered)
    Route::post(
        '/tasks/{task_id}/set-delivery-fee',
        [DeliveryController::class, 'setDeliveryFee']
    );

    // تحديث حالة مهمة التوصيل
    Route::put(
        '/tasks/{task_id}/update-status',
        [DeliveryController::class, 'updateTaskStatus']
    );

});


    //Payment Gateway
    // Route::prefix('payment')->group(function () {
    //     Route::post('/create-checkout-session', [StripeController::class, 'createCheckoutSession']);

    // });
});

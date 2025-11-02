<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;



// Test email verification (remove in production)
// Route::get('/test-email/{email}', function($email) {
//     try {
//         $user =User::where('email', $email)->first();
//         if (!$user) {
//             return response()->json(['message' => 'User not found'], 404);
//         }
        
//         $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
//             'verification.verify',
//             now()->addMinutes(60),
//             ['id' => $user->id, 'hash' => sha1($user->email)]
//         );
        
//         \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\VerificationEmail($user, $verificationUrl));
        
//         return response()->json([
//             'message' => 'Test email sent successfully',
//             'verification_url' => $verificationUrl
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'message' => 'Failed to send test email',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });



// Specializations endpoints
Route::get('/specializations', [SpecializationController::class, 'index']);

// Uploads endpoints
Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

// Auth endpoints
Route::prefix('auth')->group(function () {
    
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);
    // Email verification routes
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->name('verification.verify');
        return redirect(env('FRONTEND_URL') . '/login?verified=true');
});


Route::middleware('auth:sanctum','verified')->prefix('auth')->group(function () {

    Route::post('logout', [LoginController::class, 'logout']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    //doctors
    Route::prefix('doctors')->group(function () {
    // Specializations
        Route::get('specializations/{id}', [DoctorController::class, 'getDoctorsBySpecialization']);
    });

    // Consultations
    Route::prefix('patient/consultations')->group(function () {
        Route::post('/book', [ConsultationController::class, 'book']);
    });

    //patients
});
Route::middleware('auth:sanctum')->post('/consultations', [ConsultationController::class, 'store']);

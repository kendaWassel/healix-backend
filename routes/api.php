<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\UploadController;



// Uploads endpoints
Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

// Specializations endpoints
Route::get('/specializations', [SpecializationController::class, 'index']);

// Auth endpoints

Route::prefix('auth')->group(function () {
    
    Route::post('register', [RegisterController::class, 'register']);
});
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout']);
});

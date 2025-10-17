<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Api\AuthController;


Route::post('/uploads', [UploadController::class, 'uploadFile']);
Route::post('/uploads/image', [UploadController::class, 'uploadImage']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
});
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

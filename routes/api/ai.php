<?php

use App\Http\Controllers\Api\AI\ChatController;
use App\Http\Controllers\Api\AI\SpeechController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {


    Route::post('/speech-to-text', [SpeechController::class, 'transcribe']);

    Route::prefix('chat')->group(function () {
        Route::post('/start', [ChatController::class, 'startChat']);
        Route::post('/send', [ChatController::class, 'sendMessage']);
        
    });
});
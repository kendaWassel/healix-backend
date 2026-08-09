<?php

use App\Http\Controllers\Api\AI\ChatController;
use App\Http\Controllers\Api\AI\SpeechController;
use App\Http\Controllers\Api\DDI\DrugInteractionController;
use App\Http\Controllers\Api\Lab\LabAnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {


    Route::post('/speech-to-text', [SpeechController::class, 'transcribe']);

    Route::prefix('chat')->group(function () {
        Route::post('/start', [ChatController::class, 'startChat']);
        Route::post('/send', [ChatController::class, 'sendMessage']);
    });
    Route::prefix('lab')->group(function () {
        Route::get('/health', [LabAnalysisController::class, 'health']);
        Route::get('/reference-ranges', [LabAnalysisController::class, 'referenceRanges']);
        Route::get('/analyses', [LabAnalysisController::class, 'index']);
        Route::get('/supported-tests', [LabAnalysisController::class, 'supportedTests']);
        // Route::get('/analyses/{id}', [LabAnalysisController::class, 'show']);
        Route::post('/analyze', [LabAnalysisController::class, 'analyze']);
        Route::get('/analyses/{id}/pdf', [LabAnalysisController::class, 'downloadPdf']);
        Route::get('/analyses/{id}/patient-pdf', [LabAnalysisController::class, 'downloadPatientPdf']);
    });
    Route::prefix('ddi')->group(function () {
        Route::get('/resolve', [DrugInteractionController::class, 'resolve']);
        Route::post('/interaction', [DrugInteractionController::class, 'checkInteraction']);
        Route::post('/interaction/batch', [DrugInteractionController::class, 'checkBatch']);
        Route::post('/screen', [DrugInteractionController::class, 'screenMedications']);
        Route::post('/allergy', [DrugInteractionController::class, 'checkAllergy']);
        Route::post('/pregnancy', [DrugInteractionController::class, 'checkPregnancy']);
        Route::post('/condition-check', [DrugInteractionController::class, 'checkConditions']);

        // Persisted history of the authenticated patient's drug-safety checks.
        Route::get('/checks', [DrugInteractionController::class, 'history']);
        Route::get('/checks/{id}', [DrugInteractionController::class, 'show']);
    });
});

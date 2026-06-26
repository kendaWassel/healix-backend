<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;

Route::middleware(['auth:sanctum', 'verified', 'role:admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/services', [AdminController::class, 'services']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'addUser']);

        Route::get('/users/{id}/attachments', [AdminController::class, 'attachments']);
        Route::patch('/users/{id}/approve', [AdminController::class, 'approveUser']);
        Route::patch('/users/{id}/reject', [AdminController::class, 'rejectUser']);
        Route::put('/users/{id}/edit', [AdminController::class, 'editUser']);
        Route::delete('/users/{id}/delete', [AdminController::class, 'deleteUser']);
    });
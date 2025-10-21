<?php

namespace App\Http\Controllers\Api\Auth;


use App\Services\AuthService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;

class RegisterController extends Controller
{
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        try {
            // Debug: Log the incoming data
            Log::info('Registration attempt', $request->validated());
            
            $result = $this->authService->register($request->validated());

            return response()->json($result, 201);
            
            
        } catch (\Exception $e) {
            // Debug: Log the error
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
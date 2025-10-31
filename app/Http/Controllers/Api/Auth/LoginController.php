<?php
namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;


class LoginController extends Controller
{
    
public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->authenticate(
            $request->email, 
            $request->password
        );
        
        if (!$result) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
        
        return response()->json($result, 200);
    }
    
    public function logout(Request $request)
    {
        $result = $this->authService->logout($request);
        if (!$result) {
            return response()->json([
                'error' => 'Logout failed'
            ], 400);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }
}



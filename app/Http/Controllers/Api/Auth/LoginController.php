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
        
        return response()->json($result);
    }
    
    public function logout(Request $request)
    {
        return $this->authService->logout($request);
    }
}



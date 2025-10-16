<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['email'=>'required|email','password'=>'required']);

        $user = User::where('email',$request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)){
            return response()->json(['error'=>'Invalid credentials'], 401);
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'access_token'=>$token,
            'token_type'=>'Bearer',
            'role'=>$user->role
        ]);
    }

    public function logout(Request $request)
{
    
    $request->user()->currentAccessToken?->delete();

    return response()->json(['message' => 'Logged out']);
}

}

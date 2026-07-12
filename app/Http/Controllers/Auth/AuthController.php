<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. validazione
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        // 2. User::create(...)
        $user = User::create([
            "name" => $validated["name"],
            "email" => $validated["email"],
            "password" => Hash::make($validated["password"])
        ]);
        // 3. genera token
        $token = JWTAuth::fromUser($user);
        // 4. return response()->json(...)
        return response()->json([
            "status" => "success",
            "message" => "Registrazione effettuata con successo",
            "data" => [
                "user" => $user,
                "token" => $token
            ]
        ]);
    }

    public function login(Request $request)
    {
        $request->validate(
            [
                "email" => "required|string|email",
                "password" => "required|string"
            ]
        );

        $credentials = $request->only('email', 'password');
        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        if (!$token = $guard->attempt($credentials)) {
            return response()->json([
                "status" => "error",
                "message" => "Credenziali non valide"
            ], 401);
        }

        return response()->json([
            "status" => "success",
            "message" => "Login effettuato con successo",
            "data" => [
                "user" => auth('api')->user(),
                "token" => $token
            ]
        ]);
    }
    public function logout(Request $request)
    {
        // invalida il token corrente

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();

        return response()->json([
            "status" => "success",
            "message" => "Logout effettuato con successo"
        ]);
    }
}

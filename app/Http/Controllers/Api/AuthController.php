<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Registro
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role_id'  => 'nullable|exists:roles,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role_id'  => $request->role_id ?? 3, // recepcionista por defecto
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user'    => $user->load('role'),
            'token'   => $token,
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $user = auth('api')->user()->load('role');

        return response()->json([
            'message' => 'Login exitoso',
            'user'    => $this->publicUser($user),
            'token'   => $token,
            'type'    => 'bearer',
        ]);
    }

    // Perfil del usuario autenticado
    public function me()
    {
        return response()->json([
            'user' => $this->publicUser(auth('api')->user()->load('role')),
        ]);
    }

    private function publicUser($user): array
    {
        return array_merge($user->toArray(), [
            'roles' => $user->getRoleNames()->values(),
            'puede_cobrar' => $user->hasAnyRole(['Administrador', 'Cajero']),
        ]);
    }

    // Logout
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    // Refrescar token
    public function refresh()
    {
        return response()->json([
            'token' => auth('api')->refresh()
        ]);
    }
}
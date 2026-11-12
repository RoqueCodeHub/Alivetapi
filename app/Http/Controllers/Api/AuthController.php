<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Throwable;
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
            'remember' => 'sometimes|boolean',
        ]);

        $credentials = $request->only('email', 'password');
        $guard = auth('api');
        $defaultTtl = (int) config('jwt.ttl');
        if ($request->boolean('remember')) {
            $guard->setTTL(60 * 24 * 14);
        }

        try {
            $token = $guard->attempt($credentials);
        } finally {
            $guard->setTTL($defaultTtl);
        }

        if (!$token) {
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo enviar el correo. Intenta más tarde.',
            ], 503);
        }

        return response()->json([
            'message' => 'Si el correo está registrado, enviaremos instrucciones para restablecer la contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'El enlace no es válido o ya expiró.',
            ], 422);
        }

        return response()->json([
            'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.',
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
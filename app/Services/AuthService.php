<?php

namespace App\Services;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly LoginLogService $logins)
    {
    }

    public function login(string $email, string $password): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            $this->logins->record(request(), false, $email, $user);

            return ApiResponse::error('Credenciales incorrectas', 401);
        }

        if ($user->isPortalCustomer()) {
            $this->logins->record(request(), false, $email, $user);

            return ApiResponse::error('Usa el portal de cliente para entrar.', 403);
        }

        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api')->plainTextToken;
        $this->logins->record(request(), true, $email, $user);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->payload($user),
        ], 'Sesión iniciada');
    }

    public function portalLogin(string $email, string $password): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active || ! $user->isPortalCustomer()) {
            $this->logins->record(request(), false, $email, $user);

            return ApiResponse::error('Credenciales incorrectas', 401);
        }

        $user->tokens()->where('name', 'portal')->delete();
        $token = $user->createToken('portal')->plainTextToken;
        $this->logins->record(request(), true, $email, $user);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->payload($user),
        ], 'Portal iniciado');
    }

    public function logout(User $user): JsonResponse
    {
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Sesión cerrada');
    }

    public function me(User $user): JsonResponse
    {
        return ApiResponse::success($this->payload($user), 'Perfil');
    }

    public function changePassword(User $user, string $current, string $new): JsonResponse
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($new),
        ]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return ApiResponse::success(null, 'Contraseña actualizada');
    }

    public function payload(User $user): array
    {
        $user->loadMissing('roles', 'permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'cliente_id' => $user->cliente_id,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}

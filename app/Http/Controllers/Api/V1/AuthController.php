<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authService->login(
            $request->validated('email'),
            $request->validated('password')
        );
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->authService->logout($request->user());
    }

    public function me(Request $request): JsonResponse
    {
        return $this->authService->me($request->user());
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );
    }
}

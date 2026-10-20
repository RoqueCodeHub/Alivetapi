<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

class LoginLogService
{
    public function record(Request $request, bool $success, string $email, ?User $user = null): void
    {
        LoginLog::query()->create([
            'user_id' => $user?->id,
            'email' => strtolower($email),
            'success' => $success,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);
    }
}

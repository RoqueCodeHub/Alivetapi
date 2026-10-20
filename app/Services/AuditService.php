<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public const SENSITIVE = ['password', 'remember_token', 'token', 'plainTextToken'];

    public function record(string $action, Model $model, ?array $before, ?array $after): void
    {
        if ($model instanceof AuditLog
            || $model instanceof \App\Models\LoginLog
            || $model instanceof \App\Models\BackupProbe) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model' => class_basename($model),
            'model_id' => $model->getKey(),
            'before' => $this->scrub($before),
            'after' => $this->scrub($after),
            'ip' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }

    private function scrub(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }
        foreach (self::SENSITIVE as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[hidden]';
            }
        }

        return $payload;
    }
}

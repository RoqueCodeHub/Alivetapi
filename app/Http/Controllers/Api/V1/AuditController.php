<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Services\BackupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(private readonly BackupService $backups)
    {
    }

    public function logs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);
        $page = AuditLog::query()
            ->with('user')
            ->when($request->query('model'), fn ($q, $m) => $q->where('model', $m))
            ->orderByDesc('id')
            ->paginate(30);

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'action' => $log->action,
                'model' => $log->model,
                'model_id' => $log->model_id,
                'before' => $log->before,
                'after' => $log->after,
                'ip' => $log->ip,
                'created_at' => optional($log->created_at)?->toDateTimeString(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Auditoría');
    }

    public function logins(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);
        $page = LoginLog::query()->with('user')->orderByDesc('id')->paginate(30);

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (LoginLog $log) => [
                'id' => $log->id,
                'email' => $log->email,
                'success' => $log->success,
                'user' => $log->user?->name,
                'ip' => $log->ip,
                'created_at' => optional($log->created_at)?->toDateTimeString(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Accesos');
    }

    public function backups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        return ApiResponse::success($this->backups->list(), 'Respaldos');
    }

    public function security(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        return ApiResponse::success([
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'force_https' => (bool) config('app.force_https'),
            'cors_origins' => config('cors.allowed_origins'),
            'backups' => count($this->backups->list()),
            'notes' => [
                'validaciones' => 'FormRequests + policies',
                'sql' => 'Eloquent / bindings',
                'xss' => 'Angular interpola; API JSON',
                'csrf' => 'API Bearer Sanctum; web VerifyCsrfToken',
                'rate_limit' => 'api 60/min; login 5/min',
                'archivos' => '.env gitignored; backups fuera de public/',
            ],
        ], 'Estado de seguridad');
    }
}

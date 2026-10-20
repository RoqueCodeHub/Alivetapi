<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Services\ReminderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canView($request), 403);
        $page = $this->reminders->listForUser(
            $request->user(),
            $request->query('audience'),
            $request->query('type')
        );

        return ApiResponse::success([
            'items' => ReminderResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Recordatorios');
    }

    public function dismiss(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless($this->canView($request), 403);

        return ApiResponse::success(new ReminderResource($this->reminders->dismiss($reminder)), 'Descartado');
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();

        return $user?->can('reminders.view')
            || $user?->can('clinic.view')
            || $user?->can('inventory.view')
            || $user?->can('reports.view_all')
            || $user?->can('cash.close')
            || false;
    }
}

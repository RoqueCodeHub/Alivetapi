<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CloseCashSessionRequest;
use App\Http\Requests\Sales\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\CashSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function __construct(private readonly CashSessionService $sessions)
    {
    }

    public function current(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('cash.open') || $request->user()?->can('sales.view'), 403);

        $session = $this->sessions->current($request->user());
        if (! $session) {
            return ApiResponse::success(null, 'Sin caja abierta');
        }

        return ApiResponse::success(new CashSessionResource($session->load('user')), 'Caja actual');
    }

    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        $session = $this->sessions->open($request->user(), (float) $request->input('opening_amount'));

        return ApiResponse::success(new CashSessionResource($session->load('user')), 'Caja abierta', 201);
    }

    public function close(CloseCashSessionRequest $request, CashSession $cashSession): JsonResponse
    {
        $closed = $this->sessions->close(
            $cashSession,
            (float) $request->input('counted_amount'),
            $request->input('notes'),
            $request->user()
        );

        return ApiResponse::success(new CashSessionResource($closed), 'Caja cerrada');
    }
}

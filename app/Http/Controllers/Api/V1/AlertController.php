<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function expiring(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        return ApiResponse::success($this->stock->expiryAlerts(), 'Alertas de vencimiento');
    }

    public function lowStock(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $items = $this->stock->lowStock()->map(function ($variante) {
            $onHand = (int) $variante->batches->sum('quantity');

            return [
                'variant_id' => $variante->id,
                'sku' => $variante->sku,
                'producto' => $variante->producto?->nombre,
                'presentation' => $variante->presentation,
                'on_hand' => $onHand,
                'min_stock' => (int) $variante->min_stock,
            ];
        })->values();

        return ApiResponse::success($items, 'Stock mínimo');
    }
}

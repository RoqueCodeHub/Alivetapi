<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\PayPurchaseRequest;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Http\Requests\Purchases\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Models\Variante;
use App\Services\PurchaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $page = $this->purchases->paginate($request->query('status'));

        return ApiResponse::success([
            'items' => PurchaseResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Compras');
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $this->purchases->create($request->validated(), $request->user());

        return ApiResponse::success(new PurchaseResource($purchase), 'Compra creada', 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $purchase->load(['supplier', 'items.variante.producto', 'items.batch', 'payments']);

        return ApiResponse::success(new PurchaseResource($purchase), 'Compra');
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', Purchase::class);

        $updated = $this->purchases->update($purchase, $request->validated());

        return ApiResponse::success(new PurchaseResource($updated), 'Compra actualizada');
    }

    public function confirm(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', Purchase::class);

        $confirmed = $this->purchases->confirm($purchase, $request->user());

        return ApiResponse::success(new PurchaseResource($confirmed), 'Compra confirmada');
    }

    public function pay(PayPurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        $updated = $this->purchases->pay($purchase, $request->validated(), $request->user());

        return ApiResponse::success(new PurchaseResource($updated), 'Pago registrado');
    }

    public function payables(): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $page = $this->purchases->payables();

        return ApiResponse::success([
            'items' => PurchaseResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Cuentas por pagar');
    }

    public function costHistory(Request $request, Variante $variante): JsonResponse
    {
        abort_unless($request->user()?->can('costs.view') || $request->user()?->can('purchases.view'), 403);

        $rows = $this->purchases->costHistory($variante)->map(fn ($row) => [
            'id' => $row->id,
            'cost' => $row->cost,
            'purchase_id' => $row->purchase_id,
            'comprobante' => $row->purchase?->comprobante,
            'created_at' => $row->created_at?->toDateTimeString(),
        ]);

        return ApiResponse::success($rows, 'Historial de costos');
    }
}

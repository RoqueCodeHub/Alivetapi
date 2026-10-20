<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordStockCountRequest;
use App\Http\Requests\Inventory\StoreStockCountRequest;
use App\Http\Resources\StockCountResource;
use App\Models\StockCount;
use App\Services\StockCountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCountController extends Controller
{
    public function __construct(private readonly StockCountService $counts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.count') || $request->user()?->can('inventory.view'), 403);

        $page = StockCount::query()->with('user')->orderByDesc('id')->paginate(15);

        return ApiResponse::success([
            'items' => StockCountResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Conteos físicos');
    }

    public function store(StoreStockCountRequest $request): JsonResponse
    {
        $count = $this->counts->create($request->user(), $request->input('notes'));

        return ApiResponse::success(new StockCountResource($count), 'Conteo creado', 201);
    }

    public function show(Request $request, StockCount $stockCount): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.count') || $request->user()?->can('inventory.view'), 403);

        $stockCount->load(['items.batch', 'items.variante.producto', 'user', 'approver']);

        return ApiResponse::success(new StockCountResource($stockCount), 'Conteo');
    }

    public function record(RecordStockCountRequest $request, StockCount $stockCount): JsonResponse
    {
        $updated = $this->counts->record($stockCount, $request->validated('items'));

        return ApiResponse::success(new StockCountResource($updated), 'Conteo registrado');
    }

    public function approve(Request $request, StockCount $stockCount): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.count'), 403);

        $updated = $this->counts->approve($stockCount, $request->user());

        return ApiResponse::success(new StockCountResource($updated), 'Conteo aprobado');
    }
}

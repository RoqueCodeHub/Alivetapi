<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\OpenBoxRequest;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Http\Resources\VariantResource;
use App\Models\Batch;
use App\Models\StockMovement;
use App\Models\Variante;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Support\CsvStreamer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $page = $this->stock->catalog($request->query('q'));
        $items = $page->getCollection()->map(function (Variante $variante) {
            $onHand = (int) $variante->batches->sum('quantity');

            return [
                'variant' => (new VariantResource($variante))->resolve(),
                'on_hand' => $onHand,
                'min_stock' => (int) $variante->min_stock,
                'low_stock' => $variante->min_stock > 0 && $onHand <= $variante->min_stock,
            ];
        })->values();

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Stock');
    }

    public function show(Request $request, Variante $variante): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $data = $this->stock->variantStock($variante->load(['producto.categoria', 'batches']));
        $data['variant'] = (new VariantResource($data['variant']))->resolve();
        $data['batches'] = $data['batches']->map(fn (Batch $b) => [
            'id' => $b->id,
            'batch_code' => $b->batch_code,
            'expiry_date' => optional($b->expiry_date)?->toDateString(),
            'quantity' => $b->quantity,
            'cost' => $b->cost,
            'sale_price' => $variante->sale_price,
            'estado' => $this->batchStatus($b),
        ])->values();
        $data['fefo'] = $data['fefo']->map(fn (Batch $b) => [
            'id' => $b->id,
            'batch_code' => $b->batch_code,
            'expiry_date' => optional($b->expiry_date)?->toDateString(),
            'quantity' => $b->quantity,
        ])->values();

        return ApiResponse::success($data, 'Stock de variante');
    }

    public function kardex(Request $request, Variante $variante): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        return ApiResponse::success($this->stock->kardex($variante), 'Kardex');
    }

    public function fefo(Request $request, Variante $variante): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $quantity = (int) $request->query('quantity', 1);
        $allocations = $this->stock->allocateFefo($variante, $quantity);

        return ApiResponse::success(array_map(fn ($row) => [
            'batch_id' => $row['batch']->id,
            'batch_code' => $row['batch']->batch_code,
            'expiry_date' => optional($row['batch']->expiry_date)?->toDateString(),
            'quantity' => $row['quantity'],
        ], $allocations), 'Asignación FEFO');
    }

    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        $result = $this->stock->receive($request->validated(), $request->user());
        $batch = $result['batch'];
        $variante = $result['variant'];

        return ApiResponse::success([
            'batch' => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'expiry_date' => optional($batch->expiry_date)?->toDateString(),
                'quantity' => $batch->quantity,
                'cost' => $batch->cost,
            ],
            'variant' => (new VariantResource($variante))->resolve(),
            'on_hand' => $result['on_hand'],
        ], 'Ingreso de stock registrado', 201);
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));
        $type = $request->input('type', StockMovement::TYPE_AJUSTE);
        $direction = $request->string('direction')->toString();

        if ($type === StockMovement::TYPE_AJUSTE) {
            $movement = $this->stock->adjust(
                $batch,
                $direction,
                $request->integer('quantity'),
                $request->string('reason')->toString(),
                $request->user()
            );
        } else {
            $dir = in_array($direction, ['salida', StockMovement::DIRECTION_OUT], true)
                ? StockMovement::DIRECTION_OUT
                : StockMovement::DIRECTION_IN;
            $movement = $this->stock->move(
                $batch,
                $type,
                $request->integer('quantity'),
                $request->user(),
                $request->string('reason')->toString(),
                null,
                $dir
            );
        }

        return ApiResponse::success([
            'id' => $movement->id,
            'type' => $movement->type,
            'direction' => $movement->direction,
            'quantity' => $movement->quantity,
            'reason' => $movement->reason,
            'batch_id' => $movement->batch_id,
        ], 'Movimiento registrado', 201);
    }

    public function openBox(OpenBoxRequest $request): JsonResponse
    {
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));
        $result = $this->stock->openBox($batch, $request->integer('boxes'), $request->user());

        return ApiResponse::success([
            'opened' => $result['opened'],
            'units' => $result['units'],
            'source_batch_id' => $result['source_batch']->id,
            'source_quantity' => $result['source_batch']->quantity,
            'unit_batch_id' => $result['unit_batch']->id,
            'unit_quantity' => $result['unit_batch']->quantity,
        ], 'Caja abierta');
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        return CsvStreamer::download(
            'inventario.csv',
            ['sku', 'producto', 'presentación', 'lote', 'vencimiento', 'cantidad', 'costo', 'stock_mínimo', 'unidad', 'unidad_venta', 'unidades_por_caja'],
            $this->stock->inventoryExportRows()
        );
    }

    public function exportKardex(Request $request, Variante $variante): StreamedResponse|JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $rows = array_map(fn ($line) => [
            $line['fecha'],
            $line['usuario'],
            $line['movimiento'],
            $line['cantidad'],
            $line['saldo'],
            $line['motivo'],
            $line['referencia'],
            $line['lote'],
        ], $this->stock->kardex($variante));

        return CsvStreamer::download(
            'kardex-'.$variante->id.'.csv',
            ['fecha', 'usuario', 'movimiento', 'cantidad', 'saldo', 'motivo', 'referencia', 'lote'],
            $rows
        );
    }

    private function batchStatus(Batch $batch): string
    {
        if ((int) $batch->quantity <= 0) {
            return 'agotado';
        }
        if ($batch->expiry_date && $batch->expiry_date->lt(now()->startOfDay())) {
            return 'vencido';
        }
        if ($batch->expiry_date && $batch->expiry_date->lte(now()->addDays(30)->endOfDay())) {
            return 'por_vencer';
        }

        return 'disponible';
    }
}

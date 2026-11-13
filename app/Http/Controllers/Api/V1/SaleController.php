<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\PaySaleRequest;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\TicketPdf;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales)
    {
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sales.create') || $request->user()?->can('inventory.view') || $request->user()?->can('sales.view'), 403);

        $page = $this->sales->catalog($request);

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => PageSize::meta($page),
        ], 'Catálogo de venta');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $page = $this->sales->paginate($request->user(), $request);

        return ApiResponse::success([
            'items' => SaleResource::collection($page->getCollection())->resolve(),
            'meta' => PageSize::meta($page),
            'summary' => $this->sales->summary($request->user()),
        ], 'Ventas');
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        if ($request->boolean('pending')) {
            $this->authorize('credit', Sale::class);
        } elseif (! $request->user()?->can('sales.charge')) {
            abort(403, 'Solo quien cobra en caja puede registrar una venta pagada.');
        }

        $existing = $this->sales->replayFor($request->user(), $request->validated('idempotency_key'));
        if ($existing) {
            return ApiResponse::success(new SaleResource($existing), 'Venta ya registrada (idempotencia)');
        }

        $sale = $this->sales->create($request->validated(), $request->user());
        $message = $sale->isPending() ? 'Venta registrada. Pendiente de cobro en caja.' : 'Venta registrada';

        return ApiResponse::success(new SaleResource($sale->load(['items.batch', 'payments', 'user', 'customer', 'pet', 'invoices'])), $message, 201);
    }

    public function pay(PaySaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('charge', Sale::class);

        $paid = $this->sales->pay($sale, $request->validated(), $request->user());
        $message = $paid->isPending()
            ? 'Abono registrado. Queda saldo pendiente.'
            : 'Pago aprobado. Entregar producto y voucher.';

        return ApiResponse::success(
            new SaleResource($paid->load(['items.batch', 'payments', 'user', 'customer', 'pet', 'invoices'])),
            $message
        );
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return ApiResponse::success(
            new SaleResource($sale->load(['items.batch', 'payments', 'user', 'customer', 'pet'])),
            'Venta'
        );
    }

    public function receipt(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return ApiResponse::success($this->sales->receipt($sale), 'Ticket');
    }

    public function ticket(Sale $sale, TicketPdf $tickets)
    {
        $this->authorize('view', $sale);
        $document = $this->sales->ticketDocument($sale);
        $pdf = $tickets->bytes($document);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$tickets->filename($document).'"',
            'Content-Length' => strlen($pdf),
        ]);
    }

    public function void(VoidSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('void', Sale::class);

        $voided = $this->sales->void($sale, $request->string('reason')->toString(), $request->user());

        return ApiResponse::success(new SaleResource($voided), 'Venta anulada');
    }

    public function returnSale(ReturnSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);
        abort_unless($request->user()?->can('sales.create'), 403);

        $return = $this->sales->returnSale($sale, $request->validated(), $request->user());

        return ApiResponse::success([
            'id' => $return->id,
            'refund_total' => $return->refund_total,
            'reason' => $return->reason,
        ], 'Devolución registrada', 201);
    }
}

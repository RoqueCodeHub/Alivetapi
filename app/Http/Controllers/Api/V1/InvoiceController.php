<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\EmitInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\BillingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('invoicing.view'), 403);
        $page = $this->billing->paginate();

        return ApiResponse::success([
            'items' => InvoiceResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Comprobantes');
    }

    public function series(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('invoicing.view'), 403);

        return ApiResponse::success($this->billing->series(), 'Series');
    }

    public function requirements(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('invoicing.view'), 403);

        return ApiResponse::success($this->billing->requirements(), 'Requisitos SUNAT/PSE');
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('invoicing.view'), 403);

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['sale', 'related', 'user'])),
            'Comprobante'
        );
    }

    public function emit(EmitInvoiceRequest $request, Sale $sale): JsonResponse
    {
        $invoice = $this->billing->emitForSale(
            $sale,
            $request->validated('type'),
            $request->user(),
            $request->safe()->only(['customer_doc_type', 'customer_doc_number', 'customer_name', 'customer_address'])
        );

        return ApiResponse::success(new InvoiceResource($invoice), 'Comprobante emitido', 201);
    }

    public function retry(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('invoicing.emit'), 403);

        return ApiResponse::success(new InvoiceResource($this->billing->retry($invoice)), 'Reenvío al PSE');
    }
}

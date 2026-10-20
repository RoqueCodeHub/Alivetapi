<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateVariantRequest;
use App\Http\Requests\Inventory\ImportInventoryRequest;
use App\Http\Resources\VariantResource;
use App\Models\Variante;
use App\Services\InventoryImportService;
use App\Services\InventoryReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryImportController extends Controller
{
    public function __construct(
        private readonly InventoryImportService $importer,
        private readonly InventoryReviewService $reviews,
    ) {
    }

    public function template(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('inventory.adjust'), 403);

        $headers = ['categoría', 'producto', 'presentación', 'cantidad', 'unidad', 'F.V.', 'costo', 'precio', 'observaciones'];
        $sample = ['Medicamentos', 'Biomec 1% L.A. — 100 mL', '', '10', 'ml', 'agosto-2028', '12.50', '25.00', ''];

        return response()->streamDownload(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        }, 'plantilla_inventario.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(ImportInventoryRequest $request): JsonResponse
    {
        $summary = $this->importer->import($request->file('file'), $request->user());

        return ApiResponse::success($summary, 'Importación de inventario');
    }

    public function pending(): JsonResponse
    {
        $this->authorize('viewAny', Variante::class);

        $page = $this->reviews->pending();

        return ApiResponse::success([
            'items' => VariantResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Pendientes de revisión');
    }

    public function resolve(UpdateVariantRequest $request, Variante $variante): JsonResponse
    {
        $this->authorize('update', Variante::class);

        $updated = $this->reviews->resolve($variante, $request->validated());

        return ApiResponse::success(new VariantResource($updated), 'Variante actualizada');
    }
}

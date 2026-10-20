<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\StoreVariantRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Requests\Catalog\UpdateVariantRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VariantResource;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Producto::class);

        $page = $this->products->search($request);

        return ApiResponse::success([
            'items' => ProductResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Productos');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Producto::class);

        $producto = $this->products->create($request->validated());

        return ApiResponse::success(new ProductResource($producto), 'Producto creado', 201);
    }

    public function show(Producto $producto): JsonResponse
    {
        $this->authorize('viewAny', Producto::class);

        return ApiResponse::success(
            new ProductResource($producto->load(['categoria', 'marca', 'proveedor', 'variantes.batches'])),
            'Producto'
        );
    }

    public function update(UpdateProductRequest $request, Producto $producto): JsonResponse
    {
        $this->authorize('update', Producto::class);

        $updated = $this->products->update($producto, $request->validated());

        return ApiResponse::success(new ProductResource($updated), 'Producto actualizado');
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $this->authorize('update', Producto::class);

        if ($producto->hasHistory()) {
            $updated = $this->products->deactivate($producto);

            return ApiResponse::success(new ProductResource($updated), 'Producto desactivado porque tiene historial');
        }

        $producto->delete();

        return ApiResponse::success(null, 'Producto eliminado');
    }

    public function storeVariant(StoreVariantRequest $request, Producto $producto): JsonResponse
    {
        $this->authorize('create', Producto::class);

        $variante = $this->products->addVariant($producto, $request->validated());

        return ApiResponse::success(new VariantResource($variante), 'Variante creada', 201);
    }

    public function updateVariant(UpdateVariantRequest $request, Variante $variante): JsonResponse
    {
        $this->authorize('update', Producto::class);

        $updated = $this->products->updateVariant($variante, $request->validated());

        return ApiResponse::success(new VariantResource($updated), 'Variante actualizada');
    }
}

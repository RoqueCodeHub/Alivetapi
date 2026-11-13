<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Models\Marca;
use App\Services\BrandService;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brands)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Marca::class);

        if (! $request->hasAny(['page', 'per_page'])) {
            return ApiResponse::success($this->brands->all(), 'Marcas');
        }

        $page = $this->brands->paginate(PageSize::resolve($request), $request->query('q'));

        return ApiResponse::success([
            'items' => $page->getCollection()->values(),
            'meta' => PageSize::meta($page),
            'summary' => $this->brands->summary(),
        ], 'Marcas');
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $this->authorize('create', Marca::class);

        return ApiResponse::success($this->brands->create($request->validated()), 'Marca creada', 201);
    }

    public function update(UpdateBrandRequest $request, Marca $marca): JsonResponse
    {
        $this->authorize('update', Marca::class);

        return ApiResponse::success($this->brands->update($marca, $request->validated()), 'Marca actualizada');
    }
}

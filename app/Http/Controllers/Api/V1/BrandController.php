<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Models\Marca;
use App\Services\BrandService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brands)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Marca::class);

        return ApiResponse::success($this->brands->all(), 'Marcas');
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

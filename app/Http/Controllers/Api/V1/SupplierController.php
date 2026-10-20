<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StoreSupplierRequest;
use App\Http\Requests\Purchases\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Proveedor;
use App\Services\SupplierService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $suppliers)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Proveedor::class);

        return ApiResponse::success(
            SupplierResource::collection($this->suppliers->all())->resolve(),
            'Proveedores'
        );
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Proveedor::class);

        $supplier = $this->suppliers->create($request->validated());

        return ApiResponse::success(new SupplierResource($supplier), 'Proveedor creado', 201);
    }

    public function update(UpdateSupplierRequest $request, Proveedor $proveedor): JsonResponse
    {
        $this->authorize('update', Proveedor::class);

        $updated = $this->suppliers->update($proveedor, $request->validated());

        return ApiResponse::success(new SupplierResource($updated), 'Proveedor actualizado');
    }
}

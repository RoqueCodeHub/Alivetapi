<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\StorePetRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PetResource;
use App\Http\Resources\SaleResource;
use App\Models\Cliente;
use App\Services\CustomerService;
use App\Services\PetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly PetService $pets,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        $page = $this->customers->search($request->query('q'));

        return ApiResponse::success([
            'items' => CustomerResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Clientes');
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Cliente::class);

        $cliente = $this->customers->create($request->validated());

        return ApiResponse::success(new CustomerResource($cliente), 'Cliente creado', 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        return ApiResponse::success(new CustomerResource($cliente->load(['persona', 'pets', 'portalUser'])), 'Cliente');
    }

    public function update(UpdateCustomerRequest $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('update', Cliente::class);

        $updated = $this->customers->update($cliente, $request->validated());

        return ApiResponse::success(new CustomerResource($updated), 'Cliente actualizado');
    }

    public function sales(Cliente $cliente): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        $page = $this->customers->sales($cliente);

        return ApiResponse::success([
            'items' => SaleResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Compras del cliente');
    }

    public function storePet(StorePetRequest $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('create', Cliente::class);

        $pet = $this->pets->create($cliente, $request->validated());

        return ApiResponse::success(new PetResource($pet), 'Mascota creada', 201);
    }
}

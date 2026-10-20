<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Portal\EnablePortalRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DewormingResource;
use App\Http\Resources\PetResource;
use App\Http\Resources\SaleResource;
use App\Http\Resources\VaccinationResource;
use App\Models\Cliente;
use App\Models\Pet;
use App\Services\AuthService;
use App\Services\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct(
        private readonly PortalService $portal,
        private readonly AuthService $auth,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->auth->portalLogin(
            $request->validated('email'),
            $request->validated('password')
        );
    }

    public function enable(EnablePortalRequest $request, Cliente $cliente): JsonResponse
    {
        $user = $this->portal->enableAccess(
            $cliente,
            $request->validated('email'),
            $request->validated('password')
        );

        return ApiResponse::success([
            'user_id' => $user->id,
            'email' => $user->email,
            'cliente_id' => $user->cliente_id,
        ], 'Acceso al portal habilitado', 201);
    }

    public function me(Request $request): JsonResponse
    {
        $cliente = $this->requireCliente($request);

        return ApiResponse::success([
            'user' => $this->auth->payload($request->user()),
            'customer' => (new CustomerResource($cliente->load(['persona', 'pets'])))->resolve(),
        ], 'Portal');
    }

    public function pets(Request $request): JsonResponse
    {
        $cliente = $this->requireCliente($request);

        return ApiResponse::success(PetResource::collection($this->portal->pets($cliente))->resolve(), 'Mascotas');
    }

    public function carnet(Request $request, Pet $pet): JsonResponse
    {
        $cliente = $this->requireCliente($request);
        $pet = $this->portal->petOf($cliente, $pet);
        $data = $this->portal->carnet($pet);

        return ApiResponse::success([
            'pet' => (new PetResource($data['pet']))->resolve(),
            'vaccinations' => VaccinationResource::collection($data['vaccinations'])->resolve(),
            'dewormings' => DewormingResource::collection($data['dewormings'])->resolve(),
        ], 'Carnet');
    }

    public function appointments(Request $request): JsonResponse
    {
        $cliente = $this->requireCliente($request);

        return ApiResponse::success([
            'upcoming' => AppointmentResource::collection($this->portal->upcoming($cliente))->resolve(),
            'all' => AppointmentResource::collection($this->portal->appointments($cliente))->resolve(),
        ], 'Citas');
    }

    public function requestAppointment(Request $request): JsonResponse
    {
        $cliente = $this->requireCliente($request);
        $data = $request->validate([
            'pet_id' => ['required', 'exists:pets,id'],
            'clinic_service_id' => ['required', 'exists:clinic_services,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = $this->portal->requestAppointment($cliente, $data);

        return ApiResponse::success(new AppointmentResource($appointment), 'Solicitud de cita registrada', 201);
    }

    public function purchases(Request $request): JsonResponse
    {
        $cliente = $this->requireCliente($request);
        $page = $this->portal->purchases($cliente);

        return ApiResponse::success([
            'items' => SaleResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Compras');
    }

    public function services(Request $request): JsonResponse
    {
        $this->requireCliente($request);

        return ApiResponse::success($this->portal->catalog(), 'Servicios');
    }

    private function requireCliente(Request $request): Cliente
    {
        abort_unless($request->user()?->can('portal.access'), 403);

        return $this->portal->cliente($request->user());
    }
}

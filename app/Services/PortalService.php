<?php

namespace App\Services;

use App\Exceptions\PortalException;
use App\Models\Appointment;
use App\Models\Cliente;
use App\Models\Deworming;
use App\Models\Pet;
use App\Models\Sale;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\VetService;
use Illuminate\Support\Facades\Hash;

class PortalService
{
    public function __construct(private readonly ClinicService $clinic)
    {
    }

    public function cliente(User $user): Cliente
    {
        if (! $user->cliente_id) {
            throw new PortalException('Esta cuenta no es de portal de cliente.');
        }
        $cliente = Cliente::query()->with('persona')->find($user->cliente_id);
        if (! $cliente) {
            throw new PortalException('Cliente no encontrado.');
        }

        return $cliente;
    }

    public function enableAccess(Cliente $cliente, string $email, string $password): User
    {
        $existing = User::query()->where('email', $email)->first();
        if ($existing && (int) $existing->cliente_id !== (int) $cliente->id) {
            throw new PortalException('El correo ya está en uso.');
        }

        $user = User::query()->updateOrCreate(
            ['cliente_id' => $cliente->id],
            [
                'name' => $cliente->displayName(),
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );
        $user->syncRoles(['Cliente']);

        return $user;
    }

    public function pets(Cliente $cliente)
    {
        return Pet::query()->where('cliente_id', $cliente->id)->orderBy('nombre')->get();
    }

    public function petOf(Cliente $cliente, Pet $pet): Pet
    {
        if ((int) $pet->cliente_id !== (int) $cliente->id) {
            throw new PortalException('La mascota no pertenece a tu cuenta.');
        }

        return $pet;
    }

    public function carnet(Pet $pet): array
    {
        return [
            'pet' => $pet,
            'vaccinations' => Vaccination::query()->where('pet_id', $pet->id)->orderByDesc('applied_at')->get(),
            'dewormings' => Deworming::query()->where('pet_id', $pet->id)->orderByDesc('applied_at')->get(),
        ];
    }

    public function appointments(Cliente $cliente)
    {
        return Appointment::query()
            ->with(['pet', 'service'])
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('scheduled_at')
            ->get();
    }

    public function upcoming(Cliente $cliente)
    {
        return Appointment::query()
            ->with(['pet', 'service'])
            ->where('cliente_id', $cliente->id)
            ->where('status', Appointment::STATUS_PENDING)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    public function requestAppointment(Cliente $cliente, array $data): Appointment
    {
        $pet = $this->petOf($cliente, Pet::query()->findOrFail($data['pet_id']));
        $service = VetService::query()->findOrFail($data['clinic_service_id']);
        if (! $service->activo) {
            throw new PortalException('El servicio no está disponible.');
        }

        return $this->clinic->createAppointment([
            'pet_id' => $pet->id,
            'clinic_service_id' => $service->id,
            'staff_user_id' => null,
            'scheduled_at' => $data['scheduled_at'],
            'notes' => $data['notes'] ?? 'Solicitud desde el portal',
        ]);
    }

    public function purchases(Cliente $cliente)
    {
        return Sale::query()
            ->with(['items', 'payments'])
            ->where('customer_id', $cliente->id)
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function catalog()
    {
        return VetService::query()->where('activo', true)->orderBy('name')->get(['id', 'code', 'name', 'duration_minutes', 'sale_price']);
    }
}

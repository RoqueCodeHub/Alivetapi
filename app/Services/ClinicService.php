<?php

namespace App\Services;

use App\Exceptions\ClinicException;
use App\Models\Appointment;
use App\Models\ClinicalConsumption;
use App\Models\Deworming;
use App\Models\MedicalAttachment;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Variante;
use App\Models\VetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ClinicService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly SaleService $sales,
    ) {
    }

    public function seedCatalog(): void
    {
        foreach (VetService::DEFAULTS as $row) {
            VetService::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['activo' => true]
            );
        }
    }

    public function services()
    {
        return VetService::query()->orderBy('name')->get();
    }

    public function createService(array $data): VetService
    {
        $data['activo'] = $data['activo'] ?? true;

        return VetService::create($data);
    }

    public function updateService(VetService $service, array $data): VetService
    {
        $service->update($data);

        return $service->fresh();
    }

    public function appointments(?string $date = null, ?int $staffId = null, ?string $status = null)
    {
        return Appointment::query()
            ->with(['pet', 'cliente.persona', 'service', 'staff'])
            ->when($date, fn ($q) => $q->whereDate('scheduled_at', $date))
            ->when($staffId, fn ($q) => $q->where('staff_user_id', $staffId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('scheduled_at')
            ->paginate(30);
    }

    public function createAppointment(array $data): Appointment
    {
        $pet = Pet::query()->findOrFail($data['pet_id']);
        $service = VetService::query()->findOrFail($data['clinic_service_id']);
        if (! $service->activo) {
            throw new ClinicException('El servicio no está activo.');
        }

        return Appointment::create([
            'cliente_id' => $pet->cliente_id,
            'pet_id' => $pet->id,
            'clinic_service_id' => $service->id,
            'staff_user_id' => $data['staff_user_id'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => Appointment::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
        ])->load(['pet', 'service', 'staff', 'cliente']);
    }

    public function updateAppointment(Appointment $appointment, array $data): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_PENDING) {
            throw new ClinicException('Solo se editan citas pendientes.');
        }
        $appointment->update($data);

        return $appointment->fresh(['pet', 'service', 'staff', 'cliente']);
    }

    public function cancel(Appointment $appointment): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_PENDING) {
            throw new ClinicException('Solo se cancelan citas pendientes.');
        }
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

        return $appointment->fresh();
    }

    public function attend(Appointment $appointment, User $user, array $data): MedicalRecord
    {
        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            throw new ClinicException('No se puede atender una cita cancelada.');
        }

        return DB::transaction(function () use ($appointment, $user, $data) {
            $appointment->update(['status' => Appointment::STATUS_ATTENDED]);
            if (isset($data['peso'])) {
                $appointment->pet->update(['peso' => $data['peso']]);
            }

            return MedicalRecord::create([
                'pet_id' => $appointment->pet_id,
                'appointment_id' => $appointment->id,
                'user_id' => $user->id,
                'type' => $data['type'] ?? 'consulta',
                'diagnostico' => $data['diagnostico'] ?? null,
                'tratamiento' => $data['tratamiento'] ?? null,
                'peso' => $data['peso'] ?? null,
                'examenes' => $data['examenes'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->load(['pet', 'user', 'attachments']);
        });
    }

    public function createRecord(Pet $pet, User $user, array $data): MedicalRecord
    {
        if (isset($data['peso'])) {
            $pet->update(['peso' => $data['peso']]);
        }

        return MedicalRecord::create([
            'pet_id' => $pet->id,
            'appointment_id' => $data['appointment_id'] ?? null,
            'user_id' => $user->id,
            'type' => $data['type'] ?? 'consulta',
            'diagnostico' => $data['diagnostico'] ?? null,
            'tratamiento' => $data['tratamiento'] ?? null,
            'peso' => $data['peso'] ?? null,
            'examenes' => $data['examenes'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['pet', 'user']);
    }

    public function updateRecord(MedicalRecord $record, array $data): MedicalRecord
    {
        $record->update($data);

        return $record->fresh(['pet', 'attachments', 'vaccinations', 'dewormings']);
    }

    public function attachFile(MedicalRecord $record, UploadedFile $file, string $kind = 'adjunto'): MedicalAttachment
    {
        $path = $file->store('clinic', 'public');

        return MedicalAttachment::create([
            'medical_record_id' => $record->id,
            'path' => $path,
            'kind' => $kind === 'foto' ? 'foto' : 'adjunto',
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    public function addVaccination(Pet $pet, array $data): Vaccination
    {
        return Vaccination::create([
            'pet_id' => $pet->id,
            'medical_record_id' => $data['medical_record_id'] ?? null,
            'vaccine_name' => $data['vaccine_name'],
            'applied_at' => $data['applied_at'] ?? now()->toDateString(),
            'next_due_at' => $data['next_due_at'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
        ]);
    }

    public function updateVaccination(Vaccination $vaccination, array $data): Vaccination
    {
        $vaccination->update($data);

        return $vaccination->fresh();
    }

    public function addDeworming(Pet $pet, array $data): Deworming
    {
        return Deworming::create([
            'pet_id' => $pet->id,
            'medical_record_id' => $data['medical_record_id'] ?? null,
            'product_name' => $data['product_name'],
            'applied_at' => $data['applied_at'] ?? now()->toDateString(),
            'next_due_at' => $data['next_due_at'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
        ]);
    }

    public function updateDeworming(Deworming $deworming, array $data): Deworming
    {
        $deworming->update($data);

        return $deworming->fresh();
    }

    public function consume(Appointment $appointment, User $user, array $data): array
    {
        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            throw new ClinicException('No se consumen insumos en una cita cancelada.');
        }

        $variante = Variante::query()->with('producto')->findOrFail($data['variant_id']);
        $qty = (int) $data['quantity'];

        return DB::transaction(function () use ($appointment, $user, $variante, $qty) {
            $allocations = $this->stock->allocateFefo($variante, $qty, true);
            $created = [];
            foreach ($allocations as $allocation) {
                $this->stock->move(
                    $allocation['batch'],
                    StockMovement::TYPE_CLINICA,
                    $allocation['quantity'],
                    $user,
                    'Insumo clínico '.$appointment->id,
                    'clinic:'.$appointment->id,
                    StockMovement::DIRECTION_OUT
                );
                $created[] = ClinicalConsumption::create([
                    'appointment_id' => $appointment->id,
                    'variant_id' => $variante->id,
                    'batch_id' => $allocation['batch']->id,
                    'quantity' => $allocation['quantity'],
                    'description' => trim(($variante->producto?->nombre ?? '').' '.$variante->presentation),
                    'charged' => false,
                ]);
            }

            return $created;
        });
    }

    public function charge(Appointment $appointment, User $user, array $data)
    {
        if ($appointment->sale_id) {
            return $this->sales->findByIdempotency('clinic-'.$appointment->id)
                ?? $appointment->sale()->with(['items', 'payments'])->first();
        }
        if ($appointment->status !== Appointment::STATUS_ATTENDED) {
            throw new ClinicException('Atiende la cita antes de cobrar.');
        }

        $appointment->load(['service', 'consumptions.variante', 'consumptions.batch']);
        $items = [[
            'item_type' => 'service',
            'description' => $appointment->service?->name ?? 'Servicio clínico',
            'quantity' => 1,
            'unit_price' => (float) ($appointment->service?->sale_price ?? 0),
        ]];
        foreach ($appointment->consumptions as $row) {
            $items[] = [
                'item_type' => 'product',
                'variant_id' => $row->variant_id,
                'batch_id' => $row->batch_id,
                'quantity' => $row->quantity,
                'unit_price' => (float) ($row->variante?->sale_price ?? 0),
                'skip_stock' => true,
            ];
        }

        $sale = $this->sales->create([
            'idempotency_key' => $data['idempotency_key'] ?? 'clinic-'.$appointment->id,
            'customer_id' => $appointment->cliente_id,
            'pet_id' => $appointment->pet_id,
            'items' => $items,
            'payments' => $data['payments'],
        ], $user);

        $appointment->update(['sale_id' => $sale->id]);
        $appointment->consumptions()->update(['charged' => true]);

        return $sale;
    }

    public function petHistory(Pet $pet): array
    {
        return [
            'pet' => $pet->load('cliente.persona'),
            'records' => MedicalRecord::query()
                ->with(['user', 'attachments', 'appointment.service'])
                ->where('pet_id', $pet->id)
                ->orderByDesc('id')
                ->get(),
            'vaccinations' => Vaccination::query()->where('pet_id', $pet->id)->orderByDesc('applied_at')->get(),
            'dewormings' => Deworming::query()->where('pet_id', $pet->id)->orderByDesc('applied_at')->get(),
            'appointments' => Appointment::query()
                ->with(['service', 'staff'])
                ->where('pet_id', $pet->id)
                ->orderByDesc('scheduled_at')
                ->limit(20)
                ->get(),
        ];
    }
}

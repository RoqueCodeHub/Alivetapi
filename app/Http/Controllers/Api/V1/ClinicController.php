<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\DewormingResource;
use App\Http\Resources\MedicalRecordResource;
use App\Http\Resources\SaleResource;
use App\Http\Resources\VaccinationResource;
use App\Http\Resources\VetServiceResource;
use App\Models\Appointment;
use App\Models\Deworming;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Models\VetService;
use App\Services\ClinicService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function __construct(private readonly ClinicService $clinic)
    {
    }

    public function services(): JsonResponse
    {
        $this->authorize('viewAny', VetService::class);
        $this->clinic->seedCatalog();

        return ApiResponse::success(VetServiceResource::collection($this->clinic->services()), 'Servicios');
    }

    public function storeService(Request $request): JsonResponse
    {
        $this->authorize('manage', VetService::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:clinic_services,code'],
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'staff_role' => ['nullable', 'in:veterinario,groomer,any'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $data['staff_role'] = $data['staff_role'] ?? 'veterinario';

        return ApiResponse::success(new VetServiceResource($this->clinic->createService($data)), 'Servicio creado', 201);
    }

    public function updateService(Request $request, VetService $vetService): JsonResponse
    {
        $this->authorize('manage', VetService::class);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'staff_role' => ['nullable', 'in:veterinario,groomer,any'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(new VetServiceResource($this->clinic->updateService($vetService, $data)), 'Servicio actualizado');
    }

    public function appointments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);
        $page = $this->clinic->appointments(
            $request->query('date'),
            $request->query('staff_id') ? (int) $request->query('staff_id') : null,
            $request->query('status')
        );

        return ApiResponse::success([
            'items' => AppointmentResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Agenda');
    }

    public function storeAppointment(Request $request): JsonResponse
    {
        $this->authorize('manage', Appointment::class);
        $data = $request->validate([
            'pet_id' => ['required', 'exists:pets,id'],
            'clinic_service_id' => ['required', 'exists:clinic_services,id'],
            'staff_user_id' => ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(new AppointmentResource($this->clinic->createAppointment($data)), 'Cita creada', 201);
    }

    public function showAppointment(Appointment $appointment): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        return ApiResponse::success(new AppointmentResource($appointment->load(['pet', 'cliente', 'service', 'staff', 'consumptions'])), 'Cita');
    }

    public function updateAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('manage', Appointment::class);
        $data = $request->validate([
            'staff_user_id' => ['sometimes', 'exists:users,id'],
            'scheduled_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'clinic_service_id' => ['sometimes', 'exists:clinic_services,id'],
        ]);

        return ApiResponse::success(new AppointmentResource($this->clinic->updateAppointment($appointment, $data)), 'Cita actualizada');
    }

    public function cancelAppointment(Appointment $appointment): JsonResponse
    {
        $this->authorize('manage', Appointment::class);

        return ApiResponse::success(new AppointmentResource($this->clinic->cancel($appointment)), 'Cita cancelada');
    }

    public function attend(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('attend', Appointment::class);
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:40'],
            'diagnostico' => ['nullable', 'string'],
            'tratamiento' => ['nullable', 'string'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'examenes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(new MedicalRecordResource($this->clinic->attend($appointment, $request->user(), $data)), 'Atención registrada');
    }

    public function consume(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('attend', Appointment::class);
        $data = $request->validate([
            'variant_id' => ['required', 'exists:variantes,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $rows = $this->clinic->consume($appointment, $request->user(), $data);

        return ApiResponse::success($rows, 'Insumo descontado', 201);
    }

    public function charge(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('charge', Appointment::class);
        $data = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:efectivo,yape,plin,tarjeta,transferencia'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $sale = $this->clinic->charge($appointment, $request->user(), $data);

        return ApiResponse::success(new SaleResource($sale->load(['items', 'payments', 'user', 'customer', 'pet'])), 'Cobro registrado', 201);
    }

    public function petHistory(Pet $pet): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        return ApiResponse::success($this->clinic->petHistory($pet), 'Historia clínica');
    }

    public function storeRecord(Request $request, Pet $pet): JsonResponse
    {
        $this->authorize('attend', MedicalRecord::class);
        $data = $request->validate([
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'type' => ['nullable', 'string', 'max:40'],
            'diagnostico' => ['nullable', 'string'],
            'tratamiento' => ['nullable', 'string'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'examenes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(new MedicalRecordResource($this->clinic->createRecord($pet, $request->user(), $data)), 'Historia creada', 201);
    }

    public function showRecord(MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('viewAny', MedicalRecord::class);

        return ApiResponse::success(new MedicalRecordResource($medicalRecord->load(['user', 'attachments', 'pet'])), 'Historia');
    }

    public function updateRecord(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('attend', MedicalRecord::class);
        $data = $request->validate([
            'diagnostico' => ['nullable', 'string'],
            'tratamiento' => ['nullable', 'string'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'examenes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        return ApiResponse::success(new MedicalRecordResource($this->clinic->updateRecord($medicalRecord, $data)), 'Historia actualizada');
    }

    public function attachRecord(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $this->authorize('attend', MedicalRecord::class);
        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
            'kind' => ['nullable', 'in:foto,adjunto'],
        ]);
        $att = $this->clinic->attachFile($medicalRecord, $request->file('file'), $request->input('kind', 'adjunto'));

        return ApiResponse::success([
            'id' => $att->id,
            'kind' => $att->kind,
            'url' => $att->url(),
        ], 'Adjunto guardado', 201);
    }

    public function storeVaccination(Request $request, Pet $pet): JsonResponse
    {
        $this->authorize('attend', Vaccination::class);
        $data = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:255'],
            'applied_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'variant_id' => ['nullable', 'exists:variantes,id'],
            'medical_record_id' => ['nullable', 'exists:medical_records,id'],
        ]);

        return ApiResponse::success(new VaccinationResource($this->clinic->addVaccination($pet, $data)), 'Vacuna registrada', 201);
    }

    public function updateVaccination(Request $request, Vaccination $vaccination): JsonResponse
    {
        $this->authorize('attend', Vaccination::class);
        $data = $request->validate([
            'vaccine_name' => ['sometimes', 'string', 'max:255'],
            'applied_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(new VaccinationResource($this->clinic->updateVaccination($vaccination, $data)), 'Vacuna actualizada');
    }

    public function storeDeworming(Request $request, Pet $pet): JsonResponse
    {
        $this->authorize('attend', Deworming::class);
        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'applied_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'variant_id' => ['nullable', 'exists:variantes,id'],
            'medical_record_id' => ['nullable', 'exists:medical_records,id'],
        ]);

        return ApiResponse::success(new DewormingResource($this->clinic->addDeworming($pet, $data)), 'Desparasitación registrada', 201);
    }

    public function updateDeworming(Request $request, Deworming $deworming): JsonResponse
    {
        $this->authorize('attend', Deworming::class);
        $data = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:255'],
            'applied_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(new DewormingResource($this->clinic->updateDeworming($deworming, $data)), 'Desparasitación actualizada');
    }
}

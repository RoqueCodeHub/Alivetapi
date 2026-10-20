<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'pet_id' => $this->pet_id,
            'mascota' => $this->whenLoaded('pet', fn () => $this->pet?->nombre),
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente?->displayName()),
            'clinic_service_id' => $this->clinic_service_id,
            'servicio' => $this->whenLoaded('service', fn () => $this->service?->name),
            'sale_price' => $this->whenLoaded('service', fn () => $this->service?->sale_price),
            'staff_user_id' => $this->staff_user_id,
            'staff' => $this->whenLoaded('staff', fn () => $this->staff?->name),
            'scheduled_at' => optional($this->scheduled_at)?->toDateTimeString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'sale_id' => $this->sale_id,
            'consumptions' => $this->whenLoaded('consumptions', fn () => $this->consumptions->map(fn ($c) => [
                'id' => $c->id,
                'description' => $c->description,
                'quantity' => $c->quantity,
                'charged' => $c->charged,
            ])->values()),
        ];
    }
}

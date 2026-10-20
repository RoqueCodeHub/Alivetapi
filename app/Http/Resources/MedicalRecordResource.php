<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'appointment_id' => $this->appointment_id,
            'user_id' => $this->user_id,
            'veterinario' => $this->whenLoaded('user', fn () => $this->user?->name),
            'type' => $this->type,
            'diagnostico' => $this->diagnostico,
            'tratamiento' => $this->tratamiento,
            'peso' => $this->peso,
            'examenes' => $this->examenes,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)?->toDateTimeString(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'kind' => $a->kind,
                'url' => $a->url(),
                'original_name' => $a->original_name,
            ])->values()),
        ];
    }
}

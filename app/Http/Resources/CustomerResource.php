<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'document_type' => $this->persona?->tipo_documento,
            'document_number' => $this->persona?->numero_documento,
            'phone' => $this->persona?->telefono,
            'notes' => $this->observaciones,
            'activo' => (bool) ($this->activo ?? true),
            'portal_enabled' => (bool) $this->portalUser,
            'pets' => PetResource::collection($this->whenLoaded('pets')),
        ];
    }
}

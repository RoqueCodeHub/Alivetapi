<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'ruc' => $this->ruc,
            'phone' => $this->telefono ?: $this->persona?->telefono,
            'email' => $this->email ?: $this->persona?->email,
            'activo' => (bool) ($this->activo ?? true),
        ];
    }
}

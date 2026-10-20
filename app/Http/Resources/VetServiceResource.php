<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VetServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'sale_price' => $this->sale_price,
            'staff_role' => $this->staff_role,
            'activo' => (bool) $this->activo,
        ];
    }
}

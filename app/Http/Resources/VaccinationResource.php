<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet_id' => $this->pet_id,
            'vaccine_name' => $this->vaccine_name,
            'applied_at' => optional($this->applied_at)?->toDateString(),
            'next_due_at' => optional($this->next_due_at)?->toDateString(),
            'variant_id' => $this->variant_id,
        ];
    }
}

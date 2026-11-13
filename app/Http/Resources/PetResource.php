<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'nombre' => $this->nombre,
            'especie' => $this->especie,
            'raza' => $this->raza,
            'sexo' => $this->sexo,
            'fecha_nacimiento' => optional($this->fecha_nacimiento)?->toDateString(),
            'peso' => $this->peso,
            'alergias' => $this->alergias,
            'foto' => null,
            'foto_url' => $this->foto ? url('/api/v1/pets/'.$this->id.'/photo') : null,
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'name' => $this->cliente->displayName(),
            ]),
        ];
    }
}

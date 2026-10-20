<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'parent_id' => $this->parent_id,
            'parent' => $this->when(
                $this->relationLoaded('parent') && $this->parent,
                fn () => [
                    'id' => $this->parent->id,
                    'nombre' => $this->parent->nombre,
                ]
            ),
            'activo' => (bool) $this->activo,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}

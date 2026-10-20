<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'presentation' => $this->presentation,
            'unit' => $this->unit,
            'sale_unit' => $this->sale_unit,
            'units_per_box' => $this->units_per_box,
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'on_hand' => $this->whenLoaded('batches', fn () => (int) $this->batches->sum('quantity')),
            'lote_count' => $this->whenLoaded('batches', fn () => $this->batches->where('quantity', '>', 0)->count()),
            'min_stock' => $this->min_stock,
            'needs_review' => (bool) $this->needs_review,
            'review_note' => $this->review_note,
            'activo' => (bool) $this->activo,
            'producto' => $this->whenLoaded('producto', fn () => [
                'id' => $this->producto->id,
                'nombre' => $this->producto->nombre,
                'categoria' => $this->producto->categoria ? [
                    'id' => $this->producto->categoria->id,
                    'nombre' => $this->producto->categoria->nombre,
                ] : null,
            ]),
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($batch) => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'expiry_date' => optional($batch->expiry_date)?->toDateString(),
                'quantity' => $batch->quantity,
                'cost' => $batch->cost,
            ])->values()->all()),
        ];
    }
}

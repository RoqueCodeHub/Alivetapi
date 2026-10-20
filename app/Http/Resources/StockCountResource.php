<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'notes' => $this->notes,
            'counted_at' => optional($this->counted_at)?->toDateTimeString(),
            'approved_at' => optional($this->approved_at)?->toDateTimeString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'variant_id' => $item->variant_id,
                        'batch_id' => $item->batch_id,
                        'batch_code' => $item->batch?->batch_code,
                        'sku' => $item->variante?->sku,
                        'producto' => $item->variante?->producto?->nombre,
                        'presentation' => $item->variante?->presentation,
                        'system_qty' => $item->system_qty,
                        'counted_qty' => $item->counted_qty,
                        'difference' => $item->difference(),
                    ];
                })->values()->all();
            }),
        ];
    }
}

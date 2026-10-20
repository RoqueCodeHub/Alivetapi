<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->displayName(),
                'ruc' => $this->supplier->ruc,
            ]),
            'comprobante' => $this->comprobante,
            'fecha' => optional($this->fecha)?->toDateString(),
            'due_date' => optional($this->due_date)?->toDateString(),
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance(),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'confirmed_at' => optional($this->confirmed_at)?->toDateTimeString(),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'sku' => $item->variante?->sku,
                    'producto' => $item->variante?->producto?->nombre,
                    'presentation' => $item->variante?->presentation,
                    'batch_id' => $item->batch_id,
                    'batch_code' => $item->batch_code,
                    'expiry_date' => optional($item->expiry_date)?->toDateString(),
                    'quantity' => $item->quantity,
                    'cost' => $item->cost,
                    'line_total' => $item->lineTotal(),
                ])->values()->all();
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn ($pay) => [
                    'id' => $pay->id,
                    'amount' => $pay->amount,
                    'method' => $pay->method,
                    'reference' => $pay->reference,
                    'paid_at' => optional($pay->paid_at)?->toDateString(),
                ])->values()->all();
            }),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document(),
            'series' => $this->series,
            'number' => $this->number,
            'cash_session_id' => $this->cash_session_id,
            'user_id' => $this->user_id,
            'cajero' => $this->whenLoaded('user', fn () => $this->user->name),
            'customer_id' => $this->customer_id,
            'pet_id' => $this->pet_id,
            'cliente' => $this->whenLoaded('customer', fn () => $this->customer?->displayName()),
            'mascota' => $this->whenLoaded('pet', fn () => $this->pet?->nombre),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'change_amount' => $this->change_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'invoices' => $this->whenLoaded('invoices', function () {
                return $this->invoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'document' => $inv->document(),
                    'document_type' => $inv->document_type,
                    'status' => $inv->status,
                    'igv' => $inv->igv,
                    'gravada' => $inv->gravada,
                    'total' => $inv->total,
                ])->values()->all();
            }),
            'idempotency_key' => $this->idempotency_key,
            'created_at' => $this->created_at?->toDateTimeString(),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'variant_id' => $item->variant_id,
                    'batch_id' => $item->batch_id,
                    'batch_code' => $item->batch?->batch_code,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'discount' => $item->discount,
                    'line_total' => $item->line_total,
                ])->values()->all();
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn ($p) => [
                    'method' => $p->method,
                    'amount' => $p->amount,
                ])->values()->all();
            }),
        ];
    }
}

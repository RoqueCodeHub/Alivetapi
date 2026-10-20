<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document(),
            'document_type' => $this->document_type,
            'sunat_type' => $this->sunat_type,
            'series' => $this->series,
            'number' => $this->number,
            'sale_id' => $this->sale_id,
            'related_document' => $this->whenLoaded('related', fn () => $this->related?->document()),
            'currency' => $this->currency,
            'tax_percent' => $this->tax_percent,
            'tax_included' => $this->tax_included,
            'gravada' => $this->gravada,
            'igv' => $this->igv,
            'total' => $this->total,
            'customer_doc_type' => $this->customer_doc_type,
            'customer_doc_number' => $this->customer_doc_number,
            'customer_name' => $this->customer_name,
            'status' => $this->status,
            'credit_note_reason' => $this->credit_note_reason,
            'credit_note_label' => $this->credit_note_label,
            'pse_driver' => $this->pse_driver,
            'pse_ticket' => $this->pse_ticket,
            'pse_message' => $this->pse_message,
            'sent_at' => $this->sent_at?->toDateTimeString(),
            'accepted_at' => $this->accepted_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

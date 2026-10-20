<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Illuminate\Support\Str;

class FakePseGateway implements PseGateway
{
    public function driverName(): string
    {
        return 'fake';
    }

    public function send(Invoice $invoice, array $payload): array
    {
        return [
            'ok' => true,
            'status' => Invoice::STATUS_ACCEPTED,
            'ticket' => 'FAKE-'.Str::upper(Str::random(10)),
            'message' => 'Aceptado por PSE simulado (no SUNAT). Configure INVOICE_PSE_DRIVER=http y un PSE/OSE autorizado.',
            'raw' => [
                'simulated' => true,
                'document' => $invoice->document(),
                'payload' => $payload,
            ],
        ];
    }
}

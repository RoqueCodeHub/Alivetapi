<?php

namespace App\Services\Invoicing;

use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class HttpPseGateway implements PseGateway
{
    public function driverName(): string
    {
        return 'http';
    }

    public function send(Invoice $invoice, array $payload): array
    {
        $url = (string) config('invoicing.http.url');
        $token = (string) config('invoicing.http.token');
        if ($url === '') {
            throw new InvoiceException('Falta INVOICE_PSE_URL del proveedor PSE/OSE.');
        }

        $request = Http::timeout((int) config('invoicing.http.timeout', 20))
            ->acceptJson()
            ->asJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, $payload);
        $raw = $response->json();
        if (! is_array($raw)) {
            $raw = ['body' => $response->body(), 'http_status' => $response->status()];
        }

        $ok = $response->successful() && ($raw['accepted'] ?? $raw['success'] ?? $raw['ok'] ?? false);
        $ticket = $raw['ticket'] ?? $raw['sunat_ticket'] ?? $raw['hash'] ?? null;

        return [
            'ok' => (bool) $ok,
            'status' => $ok ? Invoice::STATUS_ACCEPTED : Invoice::STATUS_REJECTED,
            'ticket' => is_string($ticket) ? $ticket : null,
            'message' => (string) ($raw['message'] ?? $raw['errors'][0] ?? $response->reason()),
            'raw' => $raw,
        ];
    }
}

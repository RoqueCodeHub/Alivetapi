<?php

namespace App\Services;

use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\Invoicing\PseGateway;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(private readonly PseGateway $pse)
    {
    }

    public function ensureSeries(): void
    {
        $defaults = [
            ['document_type' => Invoice::TYPE_BOLETA, 'affects' => Invoice::TYPE_BOLETA, 'series' => 'B001', 'description' => 'Boleta electrónica'],
            ['document_type' => Invoice::TYPE_FACTURA, 'affects' => Invoice::TYPE_FACTURA, 'series' => 'F001', 'description' => 'Factura electrónica'],
            ['document_type' => Invoice::TYPE_CREDIT_NOTE, 'affects' => Invoice::TYPE_BOLETA, 'series' => 'BC01', 'description' => 'Nota de crédito (boletas)'],
            ['document_type' => Invoice::TYPE_CREDIT_NOTE, 'affects' => Invoice::TYPE_FACTURA, 'series' => 'FC01', 'description' => 'Nota de crédito (facturas)'],
        ];
        foreach ($defaults as $row) {
            InvoiceSeries::query()->firstOrCreate(
                ['series' => $row['series']],
                $row + ['next_number' => 1, 'active' => true]
            );
        }
    }

    public function paginate(): LengthAwarePaginator
    {
        return Invoice::query()->with(['sale', 'user', 'related'])->orderByDesc('id')->paginate(20);
    }

    public function series(): array
    {
        $this->ensureSeries();

        return InvoiceSeries::query()->orderBy('series')->get()->map(fn (InvoiceSeries $row) => [
            'id' => $row->id,
            'document_type' => $row->document_type,
            'affects' => $row->affects,
            'series' => $row->series,
            'next_number' => $row->next_number,
            'active' => $row->active,
            'description' => $row->description,
        ])->all();
    }

    public function requirements(): array
    {
        $issuer = config('invoicing.issuer');

        return [
            'igv' => (float) config('invoicing.igv'),
            'tax_included' => (bool) config('invoicing.tax_included'),
            'currency' => config('invoicing.currency'),
            'driver' => $this->pse->driverName(),
            'issuer_ruc_configured' => filled($issuer['ruc'] ?? null),
            'pse_url_configured' => filled(config('invoicing.http.url')),
            'notes' => [
                'pse' => 'La emisión a SUNAT debe ir por un PSE/OSE autorizado. El driver fake solo simula aceptación local.',
                'series' => 'Series B001/F001/BC01/FC01 independientes del ticket interno de POS.',
                'boleta' => 'DNI/CE/pasaporte, o sin documento si el total no supera S/ '.config('invoicing.boleta_without_doc_max').'.',
                'factura' => 'Obliga RUC (11 dígitos) y razón social del cliente.',
                'igv' => 'Precios de venta se tratan con IGV incluido (18%) salvo INVOICE_TAX_INCLUDED=false.',
                'contador' => 'Validar con el contador RUC emisor, domicilio fiscal, series dadas de alta y el contrato del PSE antes de producción.',
            ],
        ];
    }

    public function emitForSale(Sale $sale, string $type, User $user, ?array $override = null): Invoice
    {
        $type = strtolower($type);
        if (! in_array($type, [Invoice::TYPE_BOLETA, Invoice::TYPE_FACTURA], true)) {
            throw new InvoiceException('Solo se emiten boleta o factura desde una venta.');
        }
        if ($sale->isVoided()) {
            throw new InvoiceException('No se puede facturar una venta anulada.');
        }

        $existing = Invoice::query()
            ->where('sale_id', $sale->id)
            ->whereIn('document_type', [Invoice::TYPE_BOLETA, Invoice::TYPE_FACTURA])
            ->latest('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $sale->loadMissing(['customer.persona', 'items', 'payments']);
        $customer = $this->customerSnapshot($sale, $type, $override);
        $this->assertCustomerForType($type, $customer, (float) $sale->total);
        $taxes = $this->splitIgv((float) $sale->total);

        $invoice = DB::transaction(function () use ($sale, $type, $user, $customer, $taxes) {
            $this->ensureSeries();
            [$series, $number] = $this->nextNumber($type, $type);

            return Invoice::query()->create([
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'document_type' => $type,
                'sunat_type' => $type === Invoice::TYPE_FACTURA ? '01' : '03',
                'series' => $series,
                'number' => $number,
                'currency' => config('invoicing.currency'),
                'tax_percent' => config('invoicing.igv'),
                'tax_included' => config('invoicing.tax_included'),
                'gravada' => $taxes['gravada'],
                'igv' => $taxes['igv'],
                'total' => $taxes['total'],
                'customer_doc_type' => $customer['doc_type'],
                'customer_doc_number' => $customer['doc_number'],
                'customer_name' => $customer['name'],
                'customer_address' => $customer['address'],
                'status' => Invoice::STATUS_QUEUED,
            ]);
        });

        return $this->dispatch($invoice);
    }

    public function creditNoteForVoid(Sale $sale, string $reason, User $user): ?Invoice
    {
        $origin = $this->acceptedOrigin($sale);
        if (! $origin) {
            return null;
        }

        return $this->creditNote($origin, (float) $origin->total, '01', $reason ?: 'Anulación de la operación', $user, null);
    }

    public function creditNoteForReturn(Sale $sale, SaleReturn $return, User $user): ?Invoice
    {
        $origin = $this->acceptedOrigin($sale);
        if (! $origin) {
            return null;
        }
        $amount = (float) $return->refund_total;
        if ($amount <= 0) {
            return null;
        }
        $code = abs($amount - (float) $origin->total) < 0.009 ? '06' : '07';

        return $this->creditNote($origin, $amount, $code, $return->reason, $user, $return->id);
    }

    public function retry(Invoice $invoice): Invoice
    {
        if ($invoice->isAccepted()) {
            return $invoice;
        }

        return $this->dispatch($invoice);
    }

    public function retryPending(): int
    {
        $count = 0;
        Invoice::query()
            ->whereIn('status', [Invoice::STATUS_QUEUED, Invoice::STATUS_REJECTED])
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice) use (&$count) {
                $this->dispatch($invoice);
                $count++;
            });

        return $count;
    }

    public function dispatch(Invoice $invoice): Invoice
    {
        $payload = $this->payload($invoice->fresh(['sale.items', 'related']));
        $result = $this->pse->send($invoice, $payload);
        $invoice->update([
            'pse_driver' => $this->pse->driverName(),
            'pse_request' => $payload,
            'pse_response' => $result['raw'],
            'pse_ticket' => $result['ticket'],
            'pse_message' => $result['message'],
            'status' => $result['status'],
            'sent_at' => now(),
            'accepted_at' => $result['ok'] ? now() : null,
        ]);

        return $invoice->fresh(['sale', 'related', 'user']);
    }

    /**
     * @return array{gravada: float, igv: float, total: float}
     */
    public function splitIgv(float $total): array
    {
        $total = round($total, 2);
        $rate = ((float) config('invoicing.igv')) / 100;
        if ($rate <= 0) {
            return ['gravada' => $total, 'igv' => 0.0, 'total' => $total];
        }
        if (config('invoicing.tax_included')) {
            $gravada = round($total / (1 + $rate), 2);
            $igv = round($total - $gravada, 2);
        } else {
            $gravada = $total;
            $igv = round($total * $rate, 2);
            $total = round($gravada + $igv, 2);
        }

        return ['gravada' => $gravada, 'igv' => $igv, 'total' => $total];
    }

    private function creditNote(
        Invoice $origin,
        float $amount,
        string $reasonCode,
        string $label,
        User $user,
        ?int $returnId
    ): Invoice {
        $existing = Invoice::query()
            ->where('related_invoice_id', $origin->id)
            ->where('document_type', Invoice::TYPE_CREDIT_NOTE)
            ->when($returnId, fn ($q) => $q->where('sale_return_id', $returnId))
            ->when(! $returnId, fn ($q) => $q->whereNull('sale_return_id'))
            ->first();
        if ($existing) {
            return $existing;
        }

        $taxes = $this->splitIgv($amount);
        $invoice = DB::transaction(function () use ($origin, $taxes, $reasonCode, $label, $user, $returnId) {
            $this->ensureSeries();
            [$series, $number] = $this->nextNumber(Invoice::TYPE_CREDIT_NOTE, $origin->document_type);

            return Invoice::query()->create([
                'sale_id' => $origin->sale_id,
                'sale_return_id' => $returnId,
                'related_invoice_id' => $origin->id,
                'user_id' => $user->id,
                'document_type' => Invoice::TYPE_CREDIT_NOTE,
                'sunat_type' => '07',
                'series' => $series,
                'number' => $number,
                'currency' => $origin->currency,
                'tax_percent' => $origin->tax_percent,
                'tax_included' => $origin->tax_included,
                'gravada' => $taxes['gravada'],
                'igv' => $taxes['igv'],
                'total' => $taxes['total'],
                'customer_doc_type' => $origin->customer_doc_type,
                'customer_doc_number' => $origin->customer_doc_number,
                'customer_name' => $origin->customer_name,
                'customer_address' => $origin->customer_address,
                'status' => Invoice::STATUS_QUEUED,
                'credit_note_reason' => $reasonCode,
                'credit_note_label' => $label,
            ]);
        });

        return $this->dispatch($invoice);
    }

    private function acceptedOrigin(Sale $sale): ?Invoice
    {
        return Invoice::query()
            ->where('sale_id', $sale->id)
            ->whereIn('document_type', [Invoice::TYPE_BOLETA, Invoice::TYPE_FACTURA])
            ->where('status', Invoice::STATUS_ACCEPTED)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function nextNumber(string $documentType, string $affects): array
    {
        $row = InvoiceSeries::query()
            ->where('document_type', $documentType)
            ->where('affects', $affects)
            ->where('active', true)
            ->lockForUpdate()
            ->first();
        if (! $row) {
            throw new InvoiceException('No hay serie activa para '.$documentType);
        }
        $number = (int) $row->next_number;
        $row->update(['next_number' => $number + 1]);

        return [$row->series, $number];
    }

    /**
     * @return array{doc_type: ?string, doc_number: ?string, name: string, address: ?string}
     */
    private function customerSnapshot(Sale $sale, string $type, ?array $override): array
    {
        $cliente = $sale->customer;
        $persona = $cliente?->persona;
        $name = $override['customer_name'] ?? $cliente?->displayName() ?? 'CLIENTE VARIOS';
        $docType = $override['customer_doc_type'] ?? $persona?->tipo_documento;
        $docNumber = $override['customer_doc_number'] ?? $persona?->numero_documento;
        $address = $override['customer_address'] ?? $persona?->direccion;

        return [
            'doc_type' => $docType ? strtoupper((string) $docType) : null,
            'doc_number' => $docNumber ? preg_replace('/\D+/', '', (string) $docNumber) : null,
            'name' => $name,
            'address' => $address,
        ];
    }

    /**
     * @param  array{doc_type: ?string, doc_number: ?string, name: string, address: ?string}  $customer
     */
    private function assertCustomerForType(string $type, array $customer, float $total): void
    {
        if ($type === Invoice::TYPE_FACTURA) {
            if (($customer['doc_type'] ?? '') !== 'RUC' || strlen((string) $customer['doc_number']) !== 11) {
                throw new InvoiceException('La factura requiere cliente con RUC de 11 dígitos.');
            }
            if (trim($customer['name']) === '' || strcasecmp($customer['name'], 'CLIENTE VARIOS') === 0) {
                throw new InvoiceException('La factura requiere razón social del cliente.');
            }

            return;
        }

        $doc = (string) ($customer['doc_number'] ?? '');
        $kind = $customer['doc_type'] ?? null;
        if ($doc === '') {
            if ($total - (float) config('invoicing.boleta_without_doc_max') > 0.009) {
                throw new InvoiceException('La boleta sin documento no puede superar S/ '.config('invoicing.boleta_without_doc_max').'.');
            }

            return;
        }
        if ($kind === 'RUC' && strlen($doc) === 11) {
            return;
        }
        if ($kind === 'DNI' && strlen($doc) !== 8) {
            throw new InvoiceException('El DNI de la boleta debe tener 8 dígitos.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Invoice $invoice): array
    {
        $issuer = config('invoicing.issuer');
        $sale = $invoice->sale;
        $items = $sale?->items?->map(function ($item) {
            $line = $this->splitIgv((float) $item->line_total);

            return [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'line_total' => (float) $item->line_total,
                'gravada' => $line['gravada'],
                'igv' => $line['igv'],
            ];
        })->values()->all() ?? [];

        return [
            'issuer' => $issuer,
            'document_type' => $invoice->document_type,
            'sunat_type' => $invoice->sunat_type,
            'series' => $invoice->series,
            'number' => $invoice->number,
            'currency' => $invoice->currency,
            'tax_percent' => (float) $invoice->tax_percent,
            'tax_included' => (bool) $invoice->tax_included,
            'gravada' => (float) $invoice->gravada,
            'igv' => (float) $invoice->igv,
            'total' => (float) $invoice->total,
            'customer' => [
                'doc_type' => $invoice->customer_doc_type,
                'doc_number' => $invoice->customer_doc_number,
                'name' => $invoice->customer_name,
                'address' => $invoice->customer_address,
            ],
            'credit_note' => $invoice->document_type === Invoice::TYPE_CREDIT_NOTE ? [
                'reason_code' => $invoice->credit_note_reason,
                'reason' => $invoice->credit_note_label,
                'affected' => $invoice->related?->document(),
            ] : null,
            'items' => $items,
            'sale_id' => $invoice->sale_id,
        ];
    }
}

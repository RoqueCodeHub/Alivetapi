<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;

interface PseGateway
{
    public function driverName(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: string, ticket: ?string, message: ?string, raw: array}
     */
    public function send(Invoice $invoice, array $payload): array;
}

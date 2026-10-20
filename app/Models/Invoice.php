<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    public const TYPE_BOLETA = 'boleta';
    public const TYPE_FACTURA = 'factura';
    public const TYPE_CREDIT_NOTE = 'nota_credito';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'sale_id',
        'sale_return_id',
        'related_invoice_id',
        'user_id',
        'document_type',
        'sunat_type',
        'series',
        'number',
        'currency',
        'tax_percent',
        'tax_included',
        'gravada',
        'igv',
        'total',
        'customer_doc_type',
        'customer_doc_number',
        'customer_name',
        'customer_address',
        'status',
        'credit_note_reason',
        'credit_note_label',
        'pse_driver',
        'pse_request',
        'pse_response',
        'pse_ticket',
        'pse_message',
        'sent_at',
        'accepted_at',
    ];

    protected $casts = [
        'tax_included' => 'boolean',
        'tax_percent' => 'decimal:2',
        'gravada' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'pse_request' => 'array',
        'pse_response' => 'array',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): string
    {
        return $this->series.'-'.str_pad((string) $this->number, 8, '0', STR_PAD_LEFT);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}

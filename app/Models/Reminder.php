<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    public const TYPE_VACUNA = 'vacuna';
    public const TYPE_DESPARASITACION = 'desparasitacion';
    public const TYPE_CITA = 'cita';
    public const TYPE_VENCIMIENTO = 'vencimiento';
    public const TYPE_STOCK = 'stock_minimo';
    public const TYPE_CAJA = 'diferencia_caja';

    public const AUDIENCE_CUSTOMER = 'customer';
    public const AUDIENCE_INTERNAL = 'internal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'type',
        'audience',
        'reference_type',
        'reference_id',
        'cliente_id',
        'title',
        'message',
        'due_at',
        'email',
        'whatsapp',
        'whatsapp_url',
        'status',
        'channel',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'due_at' => 'date',
        'sent_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function isInternal(): bool
    {
        return $this->audience === self::AUDIENCE_INTERNAL;
    }
}

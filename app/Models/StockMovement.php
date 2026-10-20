<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_INICIAL = 'inicial';
    public const TYPE_COMPRA = 'compra';
    public const TYPE_VENTA = 'venta';
    public const TYPE_AJUSTE = 'ajuste';
    public const TYPE_DEVOLUCION = 'devolución';
    public const TYPE_MERMA = 'merma';
    public const TYPE_VENCIDO = 'vencido';

    public const TYPE_CLINICA = 'clinica';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const INBOUND_TYPES = [
        self::TYPE_INICIAL,
        self::TYPE_COMPRA,
        self::TYPE_DEVOLUCION,
    ];

    public const OUTBOUND_TYPES = [
        self::TYPE_VENTA,
        self::TYPE_MERMA,
        self::TYPE_VENCIDO,
        self::TYPE_CLINICA,
    ];

    public function signedQuantity(): int
    {
        return $this->direction === self::DIRECTION_OUT ? -1 * $this->quantity : $this->quantity;
    }

    protected $fillable = [
        'variant_id',
        'batch_id',
        'type',
        'direction',
        'quantity',
        'reason',
        'user_id',
        'reference',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

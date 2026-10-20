<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';

    public const PAYMENT_PENDIENTE = 'pendiente';
    public const PAYMENT_PARCIAL = 'parcial';
    public const PAYMENT_PAGADO = 'pagado';

    protected $fillable = [
        'supplier_id',
        'comprobante',
        'fecha',
        'total',
        'paid_amount',
        'status',
        'payment_status',
        'due_date',
        'notes',
        'user_id',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }
}

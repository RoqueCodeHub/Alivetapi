<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';

    public const PAYMENT_PENDING = 'pendiente';
    public const PAYMENT_PAID = 'pagado';

    protected $fillable = [
        'cash_session_id',
        'user_id',
        'customer_id',
        'pet_id',
        'series',
        'number',
        'subtotal',
        'discount',
        'total',
        'change_amount',
        'payment_status',
        'status',
        'idempotency_key',
        'notes',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'customer_id');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING
            || $this->payment_status === self::PAYMENT_PENDING;
    }

    public function document(): string
    {
        return $this->series.'-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function cashNet(): float
    {
        $cash = (float) $this->payments->where('method', 'efectivo')->sum('amount');

        return round($cash - (float) $this->change_amount, 2);
    }
}

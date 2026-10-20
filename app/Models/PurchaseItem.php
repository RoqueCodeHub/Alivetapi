<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'variant_id',
        'batch_id',
        'batch_code',
        'expiry_date',
        'quantity',
        'cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function lineTotal(): float
    {
        return round($this->quantity * (float) $this->cost, 2);
    }
}

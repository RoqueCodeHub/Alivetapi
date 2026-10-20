<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'variant_id',
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

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'sale_id',
        'item_type',
        'variant_id',
        'batch_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function returnedQuantity(): int
    {
        return (int) $this->returnItems()->sum('quantity');
    }
}

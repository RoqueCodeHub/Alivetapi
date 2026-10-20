<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variante extends Model
{
    protected $fillable = [
        'producto_id',
        'sku',
        'barcode',
        'presentation',
        'unit',
        'sale_unit',
        'units_per_box',
        'cost_price',
        'sale_price',
        'min_stock',
        'needs_review',
        'review_note',
        'activo',
    ];

    protected $casts = [
        'units_per_box' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_stock' => 'integer',
        'needs_review' => 'boolean',
        'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    public function withoutPrice(): bool
    {
        return $this->sale_price === null;
    }
}

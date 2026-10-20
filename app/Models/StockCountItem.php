<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'variant_id',
        'batch_id',
        'system_qty',
        'counted_qty',
    ];

    protected $casts = [
        'system_qty' => 'integer',
        'counted_qty' => 'integer',
    ];

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function difference(): ?int
    {
        if ($this->counted_qty === null) {
            return null;
        }

        return $this->counted_qty - $this->system_qty;
    }
}

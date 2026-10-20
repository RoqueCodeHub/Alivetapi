<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostHistory extends Model
{
    protected $fillable = [
        'variant_id',
        'purchase_id',
        'purchase_item_id',
        'cost',
        'user_id',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

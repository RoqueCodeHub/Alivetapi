<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    protected $fillable = [
        'summary_date',
        'sales_count',
        'subtotal',
        'discount',
        'total',
        'cost',
        'refund_total',
        'profit',
        'items_sold',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'cost' => 'decimal:2',
        'refund_total' => 'decimal:2',
        'profit' => 'decimal:2',
        'items_sold' => 'integer',
        'sales_count' => 'integer',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSeries extends Model
{
    protected $fillable = [
        'document_type',
        'affects',
        'series',
        'next_number',
        'active',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}

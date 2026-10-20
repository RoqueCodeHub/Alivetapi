<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalConsumption extends Model
{
    protected $fillable = [
        'appointment_id',
        'medical_record_id',
        'variant_id',
        'batch_id',
        'quantity',
        'description',
        'charged',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'charged' => 'boolean',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}

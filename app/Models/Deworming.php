<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deworming extends Model
{
    protected $fillable = [
        'pet_id',
        'medical_record_id',
        'product_name',
        'applied_at',
        'next_due_at',
        'variant_id',
    ];

    protected $casts = [
        'applied_at' => 'date',
        'next_due_at' => 'date',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const STATUS_PENDING = 'pendiente';
    public const STATUS_ATTENDED = 'atendida';
    public const STATUS_CANCELLED = 'cancelada';

    protected $fillable = [
        'cliente_id',
        'pet_id',
        'clinic_service_id',
        'staff_user_id',
        'scheduled_at',
        'status',
        'notes',
        'sale_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(VetService::class, 'clinic_service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ClinicalConsumption::class);
    }
}

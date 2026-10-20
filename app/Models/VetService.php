<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VetService extends Model
{
    public const ROLE_VET = 'veterinario';
    public const ROLE_GROOMER = 'groomer';
    public const ROLE_ANY = 'any';

    public const DEFAULTS = [
        ['code' => 'consulta', 'name' => 'Consulta', 'duration_minutes' => 30, 'sale_price' => 40, 'staff_role' => self::ROLE_VET],
        ['code' => 'vacunacion', 'name' => 'Vacunación', 'duration_minutes' => 20, 'sale_price' => 25, 'staff_role' => self::ROLE_VET],
        ['code' => 'desparasitacion', 'name' => 'Desparasitación', 'duration_minutes' => 15, 'sale_price' => 20, 'staff_role' => self::ROLE_VET],
        ['code' => 'bano', 'name' => 'Baño', 'duration_minutes' => 45, 'sale_price' => 30, 'staff_role' => self::ROLE_GROOMER],
        ['code' => 'peluqueria', 'name' => 'Peluquería', 'duration_minutes' => 60, 'sale_price' => 45, 'staff_role' => self::ROLE_GROOMER],
        ['code' => 'cirugia', 'name' => 'Cirugía', 'duration_minutes' => 120, 'sale_price' => 250, 'staff_role' => self::ROLE_VET],
        ['code' => 'hospitalizacion', 'name' => 'Hospitalización', 'duration_minutes' => 1440, 'sale_price' => 80, 'staff_role' => self::ROLE_VET],
        ['code' => 'laboratorio', 'name' => 'Laboratorio', 'duration_minutes' => 30, 'sale_price' => 35, 'staff_role' => self::ROLE_VET],
    ];

    protected $table = 'clinic_services';

    protected $fillable = [
        'code',
        'name',
        'duration_minutes',
        'sale_price',
        'staff_role',
        'activo',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'activo' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'clinic_service_id');
    }
}

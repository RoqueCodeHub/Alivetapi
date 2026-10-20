<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';

    protected $fillable = [
        'nombres',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'telefono',
        'email',
        'direccion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Accessor: nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    public function proveedor()
    {
        return $this->hasOne(Proveedor::class);
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }

    public function trabajador()
    {
        return $this->hasOne(Trabajador::class);
    }
}

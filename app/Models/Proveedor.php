<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'persona_id',
        'empresa',
        'nombre',
        'ruc',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function displayName(): string
    {
        return $this->nombre
            ?: $this->empresa
            ?: trim(($this->persona?->nombres ?? '').' '.($this->persona?->apellidos ?? ''))
            ?: 'Proveedor #'.$this->id;
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}

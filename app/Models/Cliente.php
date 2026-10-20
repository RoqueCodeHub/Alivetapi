<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'persona_id',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function displayName(): string
    {
        return trim(($this->persona?->nombres ?? '').' '.($this->persona?->apellidos ?? '')) ?: 'Cliente #'.$this->id;
    }

    public function documento(): ?string
    {
        return $this->persona?->numero_documento;
    }

    public function pets()
    {
        return $this->hasMany(Pet::class, 'cliente_id');
    }

    public function posSales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function portalUser()
    {
        return $this->hasOne(User::class, 'cliente_id');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}

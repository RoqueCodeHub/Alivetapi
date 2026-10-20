<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'cliente_id',
        'trabajador_id',
        'total',
        'descuento',
        'estado',
        'metodo_pago',
        'monto_pagado',
        'observaciones',
    ];

    protected $casts = [
        'total'    => 'decimal:2',
        'descuento' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }
}

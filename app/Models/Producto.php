<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'categoria_id',
        'marca_id',
        'proveedor_id',
        'especie',
        'tipo',
        'presentacion',
        'talla',
        'precio_compra',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
        'unidad_medida',
        'fecha_vencimiento',
        'requiere_receta',
        'requires_batch',
        'activo',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock_actual' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'requiere_receta' => 'boolean',
        'requires_batch' => 'boolean',
        'activo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }

    public function hasHistory(): bool
    {
        return $this->movimientos()->exists()
            || $this->variantes()->exists()
            || $this->variantes()->whereHas('batches')->exists()
            || $this->variantes()->whereHas('stockMovements')->exists();
    }

    public function onHandStock(): int
    {
        if ($this->relationLoaded('variantes') && $this->variantes->contains(fn ($variante) => $variante->relationLoaded('batches'))) {
            return (int) $this->variantes->sum(fn ($variante) => $variante->relationLoaded('batches')
                ? $variante->batches->sum('quantity')
                : 0);
        }

        return (int) $this->getAttribute('stock_actual');
    }

    public function loteCount(): int
    {
        if (! $this->relationLoaded('variantes')) {
            return 0;
        }

        return (int) $this->variantes->sum(function ($variante) {
            if (! $variante->relationLoaded('batches')) {
                return 0;
            }

            return $variante->batches->where('quantity', '>', 0)->count();
        });
    }

    public function salePriceFrom(): float
    {
        if ($this->relationLoaded('variantes')) {
            $prices = $this->variantes
                ->map(fn ($variante) => (float) ($variante->sale_price ?: 0))
                ->filter(fn ($price) => $price > 0);
            if ($prices->isNotEmpty()) {
                return (float) $prices->min();
            }
        }

        return (float) $this->precio_venta;
    }

    public function stockBajo(): bool
    {
        return $this->onHandStock() <= $this->stock_minimo;
    }
}

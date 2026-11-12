<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'principio_activo',
        'concentracion',
        'tipo_receta',
        'especies_destino',
        'cadena_frio',
        'tiempo_retiro_dias',
        'permite_fraccionamiento',
        'unidad_dispensacion',
        'factor_conversion',
        'precio_venta_dosis',
        'codigo_lote',
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
        'especies_destino' => 'array',
        'cadena_frio' => 'boolean',
        'permite_fraccionamiento' => 'boolean',
        'tiempo_retiro_dias' => 'integer',
        'factor_conversion' => 'decimal:2',
        'precio_venta_dosis' => 'decimal:2',
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

    public function batchQuantity(): int
    {
        if (
            $this->relationLoaded('variantes')
            && $this->variantes->isNotEmpty()
            && $this->variantes->every(fn ($variante) => $variante->relationLoaded('batches'))
        ) {
            return (int) $this->variantes->sum(fn ($variante) => $variante->batches->sum('quantity'));
        }

        if ($this->exists) {
            return (int) DB::table('batches')
                ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
                ->where('variantes.producto_id', $this->id)
                ->sum('batches.quantity');
        }

        return (int) $this->getAttribute('stock_actual');
    }

    public function onHandStock(): int
    {
        return $this->batchQuantity();
    }

    public function hasBatches(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return DB::table('batches')
            ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
            ->where('variantes.producto_id', $this->id)
            ->exists();
    }

    /** Suma de lotes cuando el producto ya tiene lotes. Si no tiene, la columna. */
    public function stockDisponible(): int
    {
        if ($this->exists && $this->hasBatches()) {
            return $this->batchQuantity();
        }

        return (int) $this->getAttribute('stock_actual');
    }

    public function syncStockActual(): int
    {
        $sum = $this->batchQuantity();
        if ((int) $this->getAttribute('stock_actual') !== $sum) {
            $this->update(['stock_actual' => $sum]);
        }
        $this->setAttribute('stock_actual', $sum);

        return $sum;
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

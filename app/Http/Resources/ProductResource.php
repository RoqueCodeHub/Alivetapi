<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'codigo' => $this->codigo,
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->whenLoaded('categoria', fn () => $this->categoria ? [
                'id' => $this->categoria->id,
                'nombre' => $this->categoria->nombre,
            ] : null),
            'marca_id' => $this->marca_id,
            'marca' => $this->whenLoaded('marca', fn () => $this->marca ? [
                'id' => $this->marca->id,
                'nombre' => $this->marca->nombre,
            ] : null),
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor ? [
                'id' => $this->proveedor->id,
                'nombre' => $this->proveedor->displayName(),
            ] : null),
            'presentacion' => $this->presentacion,
            'talla' => $this->talla,
            'variant_count' => (int) ($this->variant_count ?? 1),
            'tallas' => $this->tallas,
            'presentaciones' => $this->presentaciones,
            'especie' => $this->especie,
            'tipo' => $this->tipo,
            'precio_compra' => $this->precio_compra,
            'precio_venta' => $this->salePriceFrom(),
            'stock_actual' => $this->onHandStock(),
            'lote_count' => $this->loteCount(),
            'stock_status' => $this->onHandStock() <= 0 ? 'sin_stock' : ($this->stockBajo() ? 'bajo' : 'ok'),
            'stock_minimo' => $this->stock_minimo,
            'unidad_medida' => $this->unidad_medida,
            'fecha_vencimiento' => optional($this->fecha_vencimiento)?->toDateString(),
            'requiere_receta' => (bool) $this->requiere_receta,
            'requires_batch' => (bool) $this->requires_batch,
            'activo' => (bool) $this->activo,
            'variantes' => VariantResource::collection($this->whenLoaded('variantes')),
        ];
    }
}

<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('producto')?->id;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'codigo' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('productos', 'codigo')->ignore($id)],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'exists:marcas,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'presentacion' => ['nullable', 'string', 'max:150'],
            'talla' => ['nullable', 'string', 'max:50'],
            'especie' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'stock_actual' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'unidad_medida' => ['nullable', 'string', 'max:50'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'requiere_receta' => ['sometimes', 'boolean'],
            'requires_batch' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
            'apply_to_group' => ['sometimes', 'boolean'],
        ];
    }
}
